<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pesan Validasi
|--------------------------------------------------------------------------
|
| Tanpa berkas ini setiap galat validasi keluar sebagai kunci mentah —
| pengguna melihat "validation.required", bukan kalimat. Ditemukan saat UAT
| test case 7 (FR-R05): kiriman kosong ke /api/tickets dijawab
| {"title":["validation.required"]}.
|
| APP_LOCALE dan APP_FALLBACK_LOCALE keduanya 'id', jadi berkas inilah satu-
| satunya sumber pesan validasi aplikasi. Isinya harus lengkap: kunci yang
| tidak ada di sini tidak punya tempat lain untuk jatuh.
|
| Nama kolom yang ramah dibaca ada di bagian 'attributes' paling bawah.
|
*/

return [

    'accepted' => 'Kolom :attribute harus disetujui.',
    'accepted_if' => 'Kolom :attribute harus disetujui bila :other bernilai :value.',
    'active_url' => 'Kolom :attribute bukan URL yang sah.',
    'after' => 'Kolom :attribute harus berisi tanggal setelah :date.',
    'after_or_equal' => 'Kolom :attribute harus berisi tanggal setelah atau sama dengan :date.',
    'alpha' => 'Kolom :attribute hanya boleh berisi huruf.',
    'alpha_dash' => 'Kolom :attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => 'Kolom :attribute hanya boleh berisi huruf dan angka.',
    'any_of' => 'Kolom :attribute tidak sah.',
    'array' => 'Kolom :attribute harus berupa larik.',
    'ascii' => 'Kolom :attribute hanya boleh berisi karakter dan simbol alfanumerik satu bita.',
    'before' => 'Kolom :attribute harus berisi tanggal sebelum :date.',
    'before_or_equal' => 'Kolom :attribute harus berisi tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => 'Kolom :attribute harus berisi antara :min sampai :max item.',
        'file' => 'Berkas :attribute harus berukuran antara :min sampai :max kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai antara :min sampai :max.',
        'string' => 'Kolom :attribute harus terdiri dari :min sampai :max karakter.',
    ],
    'boolean' => 'Kolom :attribute harus bernilai benar atau salah.',
    'can' => 'Kolom :attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi kolom :attribute tidak cocok.',
    'contains' => 'Kolom :attribute tidak memuat nilai yang diwajibkan.',
    'current_password' => 'Kata sandi salah.',
    'date' => 'Kolom :attribute bukan tanggal yang sah.',
    'date_equals' => 'Kolom :attribute harus berisi tanggal yang sama dengan :date.',
    'date_format' => 'Kolom :attribute tidak cocok dengan format :format.',
    'decimal' => 'Kolom :attribute harus memiliki :decimal angka di belakang koma.',
    'declined' => 'Kolom :attribute harus ditolak.',
    'declined_if' => 'Kolom :attribute harus ditolak bila :other bernilai :value.',
    'different' => 'Kolom :attribute dan :other harus berbeda.',
    'digits' => 'Kolom :attribute harus terdiri dari :digits angka.',
    'digits_between' => 'Kolom :attribute harus terdiri dari :min sampai :max angka.',
    'dimensions' => 'Kolom :attribute memiliki dimensi gambar yang tidak sah.',
    'distinct' => 'Kolom :attribute berisi nilai yang kembar.',
    'doesnt_contain' => 'Kolom :attribute tidak boleh memuat nilai tersebut.',
    'doesnt_end_with' => 'Kolom :attribute tidak boleh diakhiri dengan salah satu dari: :values.',
    'doesnt_start_with' => 'Kolom :attribute tidak boleh diawali dengan salah satu dari: :values.',
    'email' => 'Kolom :attribute harus berupa alamat surel yang sah.',
    'encoding' => 'Kolom :attribute harus memakai penyandian :encoding.',
    'ends_with' => 'Kolom :attribute harus diakhiri dengan salah satu dari: :values.',
    'enum' => 'Pilihan :attribute tidak sah.',
    'exists' => 'Pilihan :attribute tidak sah.',
    'extensions' => 'Kolom :attribute harus berekstensi salah satu dari: :values.',
    'file' => 'Kolom :attribute harus berupa berkas.',
    'filled' => 'Kolom :attribute wajib diisi.',
    'gt' => [
        'array' => 'Kolom :attribute harus berisi lebih dari :value item.',
        'file' => 'Berkas :attribute harus lebih besar dari :value kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai lebih dari :value.',
        'string' => 'Kolom :attribute harus lebih panjang dari :value karakter.',
    ],
    'gte' => [
        'array' => 'Kolom :attribute harus berisi :value item atau lebih.',
        'file' => 'Berkas :attribute harus berukuran :value kilobita atau lebih.',
        'numeric' => 'Kolom :attribute harus bernilai :value atau lebih.',
        'string' => 'Kolom :attribute harus terdiri dari :value karakter atau lebih.',
    ],
    'hex_color' => 'Kolom :attribute harus berupa warna heksadesimal yang sah.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => 'Pilihan :attribute tidak sah.',
    'in_array' => 'Kolom :attribute tidak ada di dalam :other.',
    'in_array_keys' => 'Kolom :attribute harus memuat sedikitnya satu kunci dari: :values.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'ip' => 'Kolom :attribute harus berupa alamat IP yang sah.',
    'ipv4' => 'Kolom :attribute harus berupa alamat IPv4 yang sah.',
    'ipv6' => 'Kolom :attribute harus berupa alamat IPv6 yang sah.',
    'json' => 'Kolom :attribute harus berupa teks JSON yang sah.',
    'list' => 'Kolom :attribute harus berupa daftar.',
    'lowercase' => 'Kolom :attribute harus ditulis dengan huruf kecil.',
    'lt' => [
        'array' => 'Kolom :attribute harus berisi kurang dari :value item.',
        'file' => 'Berkas :attribute harus lebih kecil dari :value kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai kurang dari :value.',
        'string' => 'Kolom :attribute harus lebih pendek dari :value karakter.',
    ],
    'lte' => [
        'array' => 'Kolom :attribute tidak boleh berisi lebih dari :value item.',
        'file' => 'Berkas :attribute harus berukuran :value kilobita atau kurang.',
        'numeric' => 'Kolom :attribute harus bernilai :value atau kurang.',
        'string' => 'Kolom :attribute harus terdiri dari :value karakter atau kurang.',
    ],
    'mac_address' => 'Kolom :attribute harus berupa alamat MAC yang sah.',
    'max' => [
        'array' => 'Kolom :attribute tidak boleh berisi lebih dari :max item.',
        'file' => 'Berkas :attribute tidak boleh lebih besar dari :max kilobita.',
        'numeric' => 'Kolom :attribute tidak boleh bernilai lebih dari :max.',
        'string' => 'Kolom :attribute tidak boleh lebih dari :max karakter.',
    ],
    'max_digits' => 'Kolom :attribute tidak boleh terdiri dari lebih dari :max angka.',
    'mimes' => 'Kolom :attribute harus berupa berkas berjenis: :values.',
    'mimetypes' => 'Kolom :attribute harus berupa berkas berjenis: :values.',
    'min' => [
        'array' => 'Kolom :attribute harus berisi sedikitnya :min item.',
        'file' => 'Berkas :attribute harus berukuran sedikitnya :min kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai sedikitnya :min.',
        'string' => 'Kolom :attribute harus terdiri dari sedikitnya :min karakter.',
    ],
    'min_digits' => 'Kolom :attribute harus terdiri dari sedikitnya :min angka.',
    'missing' => 'Kolom :attribute harus tidak ada.',
    'missing_if' => 'Kolom :attribute harus tidak ada bila :other bernilai :value.',
    'missing_unless' => 'Kolom :attribute harus tidak ada kecuali :other bernilai :value.',
    'missing_with' => 'Kolom :attribute harus tidak ada bila :values ada.',
    'missing_with_all' => 'Kolom :attribute harus tidak ada bila seluruh :values ada.',
    'multiple_of' => 'Kolom :attribute harus merupakan kelipatan dari :value.',
    'not_in' => 'Pilihan :attribute tidak sah.',
    'not_regex' => 'Format kolom :attribute tidak sah.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'password' => [
        'letters' => 'Kolom :attribute harus memuat sedikitnya satu huruf.',
        'mixed' => 'Kolom :attribute harus memuat sedikitnya satu huruf besar dan satu huruf kecil.',
        'numbers' => 'Kolom :attribute harus memuat sedikitnya satu angka.',
        'symbols' => 'Kolom :attribute harus memuat sedikitnya satu simbol.',
        'uncompromised' => 'Kolom :attribute pernah bocor dalam kebocoran data. Silakan pilih nilai lain.',
    ],
    'present' => 'Kolom :attribute harus ada.',
    'present_if' => 'Kolom :attribute harus ada bila :other bernilai :value.',
    'present_unless' => 'Kolom :attribute harus ada kecuali :other bernilai :value.',
    'present_with' => 'Kolom :attribute harus ada bila :values ada.',
    'present_with_all' => 'Kolom :attribute harus ada bila seluruh :values ada.',
    'prohibited' => 'Kolom :attribute tidak diizinkan.',
    'prohibited_if' => 'Kolom :attribute tidak diizinkan bila :other bernilai :value.',
    'prohibited_if_accepted' => 'Kolom :attribute tidak diizinkan bila :other disetujui.',
    'prohibited_if_declined' => 'Kolom :attribute tidak diizinkan bila :other ditolak.',
    'prohibited_unless' => 'Kolom :attribute tidak diizinkan kecuali :other ada di dalam :values.',
    'prohibits' => 'Kolom :attribute membuat :other tidak diizinkan.',
    'regex' => 'Format kolom :attribute tidak sah.',
    'required' => 'Kolom :attribute wajib diisi.',
    'required_array_keys' => 'Kolom :attribute harus memuat entri untuk: :values.',
    'required_if' => 'Kolom :attribute wajib diisi bila :other bernilai :value.',
    'required_if_accepted' => 'Kolom :attribute wajib diisi bila :other disetujui.',
    'required_if_declined' => 'Kolom :attribute wajib diisi bila :other ditolak.',
    'required_unless' => 'Kolom :attribute wajib diisi kecuali :other ada di dalam :values.',
    'required_with' => 'Kolom :attribute wajib diisi bila :values ada.',
    'required_with_all' => 'Kolom :attribute wajib diisi bila seluruh :values ada.',
    'required_without' => 'Kolom :attribute wajib diisi bila :values tidak ada.',
    'required_without_all' => 'Kolom :attribute wajib diisi bila seluruh :values tidak ada.',
    'same' => 'Kolom :attribute dan :other harus sama.',
    'size' => [
        'array' => 'Kolom :attribute harus berisi :size item.',
        'file' => 'Berkas :attribute harus berukuran :size kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai :size.',
        'string' => 'Kolom :attribute harus terdiri dari :size karakter.',
    ],
    'starts_with' => 'Kolom :attribute harus diawali dengan salah satu dari: :values.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'timezone' => 'Kolom :attribute harus berupa zona waktu yang sah.',
    'unique' => 'Kolom :attribute sudah dipakai.',
    'uploaded' => 'Kolom :attribute gagal diunggah.',
    'uppercase' => 'Kolom :attribute harus ditulis dengan huruf besar.',
    'url' => 'Kolom :attribute harus berupa URL yang sah.',
    'ulid' => 'Kolom :attribute harus berupa ULID yang sah.',
    'uuid' => 'Kolom :attribute harus berupa UUID yang sah.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Khusus per Kolom
    |--------------------------------------------------------------------------
    |
    | Dipakai hanya bila pesan bawaan di atas terasa mengambang untuk kolom
    | tertentu — mis. "Kolom Layanan wajib diisi" kurang menuntun dibanding
    | "Pilih Layanan terlebih dahulu".
    |
    */

    'custom' => [
        'service_name' => [
            'required' => 'Layanan wajib dipilih.',
        ],
        'subcategory_name' => [
            'required' => 'Sub Kategori wajib dipilih.',
        ],
        'subject_name' => [
            'required' => 'Subjek wajib diisi.',
        ],
        'issue_category' => [
            'required' => 'Kategori Masalah wajib diisi.',
        ],
        'sla_policy_id' => [
            'required' => 'Prioritas wajib dipilih.',
        ],
        'approver_id' => [
            'required' => 'Approver wajib dipilih untuk tiket yang memerlukan approval.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama Kolom
    |--------------------------------------------------------------------------
    |
    | Supaya pesan menyebut "Deskripsi", bukan "description".
    |
    */

    'attributes' => [
        'title' => 'Judul',
        'description' => 'Deskripsi',
        'service_id' => 'Layanan',
        'service_name' => 'Layanan',
        'subcategory_name' => 'Sub Kategori',
        'subject_name' => 'Subjek',
        'issue_category' => 'Kategori Masalah',
        'category' => 'Kategori',
        'sla_policy_id' => 'Prioritas',
        'approver_id' => 'Approver',
        'catalog_subject_id' => 'Subjek',
        'requester_name' => 'Nama Pemohon',
        'email' => 'Surel',
        'password' => 'Kata Sandi',
        'name' => 'Nama',
        'phone' => 'Telepon',
    ],

];
