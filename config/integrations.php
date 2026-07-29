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
];
