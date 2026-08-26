/**
 * Run with: node --test resources/js/lib/attachmentUpload.test.js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { attachmentFailureNotice } from './attachmentUpload.js';

test('tidak ada pesan bila semua lampiran berhasil', () => {
    assert.equal(attachmentFailureNotice([]), '');
});

/*
 | Tiket dibuat lebih dulu, lampirannya diunggah menyusul satu per satu.
 | Sebelum ini kegagalan tahap kedua ditelan diam-diam: layar tetap berkata
 | "Ticket … submitted", dan pengguna pergi mengira buktinya ikut terkirim
 | padahal tiketnya kosong.
 |
 | Ditemukan saat UAT test case 46.
 */
test('menyebut nama berkas yang gagal, bukan sekadar jumlahnya', () => {
    const pesan = attachmentFailureNotice([
        { name: 'bukti-error.png', reason: 'Kolom file harus berupa berkas berjenis: png, jpg, jpeg, pdf, mp4, mov, webm.' },
    ]);

    assert.match(pesan, /bukti-error\.png/);
    assert.match(pesan, /tidak ikut terkirim/i);
});

test('menyertakan alasan dari server bila ada', () => {
    const pesan = attachmentFailureNotice([
        { name: 'bukti-error.png', reason: 'Ukuran berkas melebihi batas.' },
    ]);

    assert.match(pesan, /Ukuran berkas melebihi batas\./);
});

test('beberapa berkas gagal disebut seluruhnya', () => {
    const pesan = attachmentFailureNotice([
        { name: 'satu.png', reason: 'Jenis berkas tidak didukung.' },
        { name: 'dua.png', reason: 'Jenis berkas tidak didukung.' },
    ]);

    assert.match(pesan, /satu\.png/);
    assert.match(pesan, /dua\.png/);
});

/*
 | Pesan harus tetap berguna walau server tidak menjelaskan apa-apa —
 | justru di situlah nama berkasnya jadi satu-satunya petunjuk.
 */
test('tanpa alasan dari server, nama berkas tetap disebut', () => {
    const pesan = attachmentFailureNotice([{ name: 'tanpa-alasan.pdf', reason: '' }]);

    assert.match(pesan, /tanpa-alasan\.pdf/);
    assert.doesNotMatch(pesan, /undefined|null/);
});

test('mengajak pengguna melampirkan ulang dari halaman tiket', () => {
    const pesan = attachmentFailureNotice([{ name: 'x.png', reason: '' }]);

    assert.match(pesan, /lampirkan ulang/i);
});
