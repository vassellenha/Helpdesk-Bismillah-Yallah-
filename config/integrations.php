<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ADHI Employee Directory
    |--------------------------------------------------------------------------
    |
    | The users table is fed from the company employee API rather than typed in
    | by hand. Same shape as the WhatsApp gateway in config/notifications.php:
    | the default "mock" driver reads a local fixture so the whole sync runs
    | offline with zero credentials, and switching to "http" plus filling the
    | .env values is the only change needed once the real endpoint exists.
    |
    */

    'employee_directory' => [

        'driver' => env('EMPLOYEE_DIRECTORY_DRIVER', 'mock'),

        'http' => [
            'base_url' => env('EMPLOYEE_DIRECTORY_URL'),
            'token' => env('EMPLOYEE_DIRECTORY_TOKEN'),
            'endpoint' => env('EMPLOYEE_DIRECTORY_ENDPOINT', '/api/v1/employees'),
            'timeout' => (int) env('EMPLOYEE_DIRECTORY_TIMEOUT', 30),

            // Where the employee array sits inside the response body. Empty
            // string means the body itself is already the array.
            'collection_key' => env('EMPLOYEE_DIRECTORY_COLLECTION_KEY', 'data'),
        ],

        'mock' => [
            'fixture' => database_path('fixtures/employees.json'),
        ],

        /*
        | PLACEHOLDER MAPPING — these left-hand keys are an educated guess at
        | the API's field names, made before the spec was available. When the
        | real payload arrives this array is the only thing that changes; no
        | class needs touching. Right-hand side must be a real users column.
        */
        'field_map' => [
            'nama_lengkap' => 'name',
            'email_korporat' => 'email',
            'username' => 'username',
            'nip' => 'nip',
            'alamat' => 'address',
            'no_telepon' => 'phone',
            'jabatan' => 'jabatan',
            'status_pegawai' => 'status',
            'kode_departemen' => 'kode_departemen',
            'kode_divisi' => 'kode_divisi',
            'kode_proyek' => 'kode_proyek',
        ],

        // Which mapped column identifies an employee across syncs. NIP is the
        // stable payroll key; email can change when someone marries or moves.
        'match_by' => 'nip',

        /*
        | Second chance when match_by finds nobody. Without it, a single digit of
        | NIP drift between the API and the helpdesk makes the sync treat an
        | existing employee as brand new, then skip them on the email unique
        | index — the feed appears to run while updating nobody.
        |
        | The match key itself is never written from a fallback match: NIP is the
        | identity everything else keys off (including CurrentActor's personas),
        | so a mismatch is reported for a human to settle rather than silently
        | rewritten. Set to null to require an exact match_by hit.
        */
        'fallback_match_by' => 'email',

        // Raw API status value => our users.status enum. Anything unlisted
        // falls back to 'active' so a new status code never locks people out.
        'status_map' => [
            'AKTIF' => 'active',
            'ACTIVE' => 'active',
            'NONAKTIF' => 'inactive',
            'INACTIVE' => 'inactive',
            'RESIGN' => 'inactive',
            'PENSIUN' => 'inactive',
        ],

        // Role given to accounts the sync creates. Existing users never have
        // their roles touched — role assignment stays an Admin decision.
        'default_role' => 'Requester',

        // Off by default: an API hiccup returning a short list would otherwise
        // deactivate real employees. Turn on only once the feed is trusted.
        'deactivate_missing' => (bool) env('EMPLOYEE_DIRECTORY_DEACTIVATE_MISSING', false),

        /*
        | What to do when the API sends a mapped field as null/"".
        |
        | false (default) — keep whatever is already on file. Safer while the
        |   feed is sparse, but it makes such a field non-authoritative for that
        |   person: an Admin edit to it will NOT be reverted by a sync. Every
        |   held-back field is counted as "kept_empty" in the run summary so this
        |   never happens invisibly.
        |
        | true — the API is the whole truth; an empty value clears the column.
        |   Only switch this on once the real feed reliably sends every field.
        */
        'overwrite_with_empty' => (bool) env('EMPLOYEE_DIRECTORY_OVERWRITE_WITH_EMPTY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | SINTA — Portal SSO
    |--------------------------------------------------------------------------
    |
    | Single sign-on against ADHI's SINTA portal, wired the same way as the
    | employee directory above: a driver interface with a "mock" implementation
    | that completes the whole login round trip offline, so the flow is testable
    | before any credential exists. Switch the driver to "oidc" and fill in the
    | endpoints once SINTA's spec is in hand — no application code changes.
    |
    | SSO authenticates people; it never creates them. An identity that has no
    | matching users row is refused, because provisioning belongs to the
    | employee-directory sync — one owner per concern.
    |
    */
    'sso' => [

        'driver' => env('SSO_DRIVER', 'mock'),

        'oidc' => [
            'client_id' => env('SSO_CLIENT_ID'),
            'client_secret' => env('SSO_CLIENT_SECRET'),
            'authorize_url' => env('SSO_AUTHORIZE_URL'),
            'token_url' => env('SSO_TOKEN_URL'),
            'userinfo_url' => env('SSO_USERINFO_URL'),
            'logout_url' => env('SSO_LOGOUT_URL'),
            'scopes' => env('SSO_SCOPES', 'openid profile email'),
            'timeout' => (int) env('SSO_TIMEOUT', 15),
        ],

        /*
        | PLACEHOLDER CLAIM NAMES — guessed ahead of SINTA's spec, exactly like
        | employee_directory.field_map. Left side is the claim SINTA returns,
        | right side is the users column it identifies. When the real spec
        | arrives this array is the only thing that changes.
        */
        'claim_map' => [
            'username' => 'username',
            'nip' => 'nip',
            'email' => 'email',
            'name' => 'name',
        ],

        /*
        | Which mapped claims identify the local account, tried in this order
        | until one matches. Email leads because that is the only thing ADHI's
        | entry URL sends; username and NIP follow so an OIDC token carrying
        | either of those still resolves through the same code.
        |
        | All three are unique columns on `users`.
        */
        'match_by' => ['email', 'username', 'nip'],

        // Kept for older config that set a single match_by; appended to the
        // chain above and de-duplicated.
        'fallback_match_by' => 'email',

        /*
        |------------------------------------------------------------------
        | Portal-initiated entry (SINTA -> helpdesk, one hop)
        |------------------------------------------------------------------
        |
        | A single URL the SINTA portal can send a signed-in employee to
        | (a "Helpdesk" tile), carrying their identity, so they land inside
        | the app without meeting a second login screen. This is the reverse
        | direction from the /auth/sso/redirect flow, where the journey
        | starts here.
        |
        | The identity arrives in the URL, so it MUST be proven — otherwise
        | appending someone else's NIP is a complete impersonation of them.
        | 'disabled' is the default on purpose: the endpoint answers 404
        | until a verification method is configured, so merely shipping this
        | opens nothing.
        |
        |   disabled : endpoint off (default)
        |   hmac     : SINTA signs the params with a shared secret
        |
        | If SINTA hands out a signed JWT instead, that becomes one more arm
        | in SsoEntry::verify() — nothing else changes.
        */
        'entry' => [
            'driver' => env('SSO_ENTRY_DRIVER', 'disabled'),

            // Shared secret for the 'hmac' driver. Lives in .env only.
            'secret' => env('SSO_ENTRY_SECRET'),

            // How old a link may be, in seconds. Short on purpose: the link
            // carries a working identity, so it is a password until it expires.
            'ttl' => (int) env('SSO_ENTRY_TTL', 120),
        ],
    ],
];
