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

        /*
        | Fixture bawaan berisi 25 pegawai contoh dan IKUT ter-commit — itu
        | sebabnya isinya harus tetap data karangan. Endpoint aslinya dibatasi
        | per IP dan bisa menolak kapan saja, jadi path-nya dibuat bisa
        | dialihkan: simpan satu tarikan asli ke tempat yang di-gitignore
        | (storage/app/private/ diabaikan seluruhnya) lalu arahkan ke situ untuk
        | mengembangkan dengan data sungguhan tanpa menyentuh jaringan.
        |
        | JANGAN pernah menimpa fixture bawaan dengan tarikan asli: 3.847 nama,
        | nomor telepon, dan surel karyawan akan masuk ke riwayat git secara
        | permanen, dan riwayat git tidak bisa benar-benar dibersihkan setelah
        | ter-push.
        |
        | Nilainya ditulis relatif terhadap root proyek (mis.
        | `storage/app/private/employees-real.json`), bukan path absolut, supaya
        | .env tidak memuat nama pengguna satu mesin tertentu.
        */
        'mock' => [
            'fixture' => ($relative = env('EMPLOYEE_DIRECTORY_MOCK_FIXTURE'))
                ? base_path($relative)
                : database_path('fixtures/employees.json'),
        ],

        /*
        | Sinkronisasi otomatis, sekali sehari.
        |
        | MATI secara bawaan, dan itu disengaja. Sinkronisasi MENIMPA data user
        | — termasuk kolom `status` yang mengunci akses. Menyalakannya di
        | lingkungan yang sumbernya masih driver "mock" berarti setiap malam
        | data orang sungguhan ditimpa fixture contoh, dan siapa pun yang tidak
        | ada di fixture itu ikut dinonaktifkan. Tidak ada layar merah yang
        | memberi tahu; yang ada cuma orang yang tiba-tiba tidak bisa masuk.
        |
        | Nyalakan setelah driver-nya "http" dan uji koneksinya hijau di layar
        | Admin → Integrations.
        |
        | Jam bawaannya 03:00 — di luar jam kerja, dan setelah penyapu log EVA
        | (02:00) selesai supaya keduanya tidak berebut database.
        */
        'auto_sync' => [
            'enabled' => (bool) env('EMPLOYEE_SYNC_AUTO', false),
            'at' => env('EMPLOYEE_SYNC_AT', '03:00'),
        ],

        /*
        | Dipetakan terhadap payload SUNGGUHAN dari
        | https://mobile.adhi.co.id/api/index.php/v2/kms2/karyawan/all
        | (diperiksa 2026-08-10: 3.847 pegawai, dibungkus {status,message,data}).
        | Kiri = nama field API, kanan = kolom users.
        |
        | Field API yang sengaja TIDAK dipetakan karena tidak ada kolom
        | padanannya: id, gender, photo, job_cluster_id, job_cluster, job_level,
        | job_position_id, division_name, proy_unit_name.
        |
        | `username` dan `address` tidak dikirim API ini sama sekali. Username
        | terisi sendiri dari email (lihat normalise()); yang tanpa email akan
        | punya username NULL — kolomnya unique tapi nullable, jadi aman.
        */
        'field_map' => [
            'name' => 'name',
            'email' => 'email',
            'npp' => 'nip',
            'phone_number' => 'phone',
            'job_position' => 'jabatan',
            'active' => 'status',

            // dept_id = kode 2 digit, dept_name = namanya ("Dept. Keuangan").
            // Ini menjawab pertanyaan lama soal kolom `unit`: ia memang
            // pasangan NAMA dari kode departemen, bukan duplikat kode.
            'dept_name' => 'unit',
            'dept_id' => 'kode_departemen',
            'division_id' => 'kode_divisi',
            'proy_unit_id' => 'kode_proyek',
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

        /*
        | Raw API status value => our users.status enum. Anything unlisted falls
        | back to 'active' so a new status code never locks people out.
        |
        | API ini mengirim field `active` berisi "Y"/"N" — bukan "AKTIF"/"RESIGN"
        | seperti yang ditebak sebelum spec ada. Ini bukan detail kosmetik:
        | selama "N" tidak terdaftar di sini, ia jatuh ke default 'active', dan
        | setiap pegawai yang sudah keluar tetap dianggap aktif — lengkap dengan
        | helpdesk_access 'enabled' saat akunnya dibuat.
        */
        'status_map' => [
            'Y' => 'active',
            'N' => 'inactive',
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
