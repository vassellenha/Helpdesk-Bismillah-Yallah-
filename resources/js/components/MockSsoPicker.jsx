import { useState } from 'react';
import SelectMenu from './SelectMenu';

/**
 * "Masuk sebagai" pada layar login mode MOCK — dulu `<select>` polos yang
 * me-render SEMUA pegawai sebagai `<option>`. Itu bekerja saat direktorinya
 * masih puluhan orang; begitu sinkron penuh mengisi ribuan pegawai sungguhan,
 * daftar alfabetis tanpa pencarian itu jadi sepenuhnya tidak terpakai — satu
 * nama harus digulir dari ribuan baris.
 *
 * Memakai SelectMenu yang sama dengan konsol Admin (sudah `searchable` untuk
 * kasus persis ini — lihat catatan di SelectMenu.jsx), bukan komponen baru.
 *
 * Navigasi lewat `window.location.href`, bukan submit form: MockSsoProvider
 * membaca EMAIL terpilih dari query string `?as=` request GET ke redirectUrl
 * itu sendiri (lihat MockSsoProvider::authorizeUrl()), jadi berpindah halaman
 * langsung ke URL itu sudah cukup — tidak ada body yang perlu dikirim.
 *
 * Emailnya yang jadi kunci, bukan NIP: itu satu-satunya identitas yang dikirim
 * portal ADHI sungguhan, jadi simulasi ini memakai jalur pencocokan yang sama
 * persis. Nama, jabatan, dan role tidak ditampilkan — ketiganya tidak
 * menentukan bisa-tidaknya seseorang masuk, dan mencantumkannya hanya membuat
 * orang mengira sebaliknya.
 */
export default function MockSsoPicker({ employees, redirectUrl }) {
    const options = employees.map((e) => ({
        value: e.email,
        // Email saja, tanpa nama: email adalah satu-satunya yang menentukan
        // akun mana yang cocok, jadi menampilkan nama di sebelahnya cuma bikin
        // orang mengira nama itu ikut dicocokkan.
        label: e.email,
    }));
    const [value, setValue] = useState(options[0]?.value ?? '');

    function submit() {
        if (!value) return;
        window.location.href = `${redirectUrl}?as=${encodeURIComponent(value)}`;
    }

    return (
        <div className="mt-4">
            <label className="mb-1.5 block text-[13px] font-medium text-gray-700 dark:text-ink-2">Masuk sebagai</label>
            <SelectMenu value={value} onChange={setValue} options={options} searchable searchPlaceholder="Cari nama atau email…" />

            <button
                type="button"
                onClick={submit}
                disabled={!value}
                className="mt-4 w-full rounded-lg bg-blue-700 dark:bg-blue-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:hover:bg-blue-400 disabled:cursor-not-allowed disabled:opacity-60"
            >
                Masuk dengan SINTA (simulasi)
            </button>
        </div>
    );
}
