/**
 * Run with: node --test resources/js/lib/auditValues.test.js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { humanizeKey, humanizeValue } from './auditValues.js';

test('nama kolom dibaca sebagai kata, bukan snake_case', () => {
    assert.equal(humanizeKey('resolution_time'), 'Resolution Time');
    assert.equal(humanizeKey('status'), 'Status');
});

test('nilai kosong ditampilkan sebagai tanda hubung', () => {
    assert.equal(humanizeValue(null), '—');
    assert.equal(humanizeValue(undefined), '—');
    assert.equal(humanizeValue(''), '—');
    assert.equal(humanizeValue([]), '—');
    assert.equal(humanizeValue({}), '—');
});

test('nilai sederhana ditampilkan apa adanya', () => {
    assert.equal(humanizeValue('3 Jam'), '3 Jam');
    assert.equal(humanizeValue(25), '25');
    assert.equal(humanizeValue(true), 'Yes');
    assert.equal(humanizeValue(false), 'No');
});

test('daftar nilai sederhana digabung dengan koma', () => {
    assert.equal(humanizeValue(['Requester', 'Team Lead']), 'Requester, Team Lead');
});

/*
 | Inti perbaikannya. Ringkasan sinkronisasi pegawai menyimpan `changes`
 | sebagai daftar objek {name, fields}; sebelum ini nilainya digabung lewat
 | Array.join() sehingga layar Audit Trail menampilkan "[object Object]"
 | sebanyak jumlah pegawai yang berubah — persis informasi yang paling
 | dibutuhkan, hilang.
 |
 | Ditemukan saat UAT test case 20.
 */
test('objek di dalam daftar tidak pernah tampil sebagai [object Object]', () => {
    const hasil = humanizeValue([
        { name: 'Denny Firmansyah', fields: ['email', 'jabatan'] },
        { name: 'Andi Pratama', fields: ['phone'] },
    ]);

    assert.doesNotMatch(hasil, /\[object Object\]/);
    assert.match(hasil, /Denny Firmansyah/);
    assert.match(hasil, /email, jabatan/);
    assert.match(hasil, /Andi Pratama/);
});

test('daftar berisi objek dipisah baris supaya tiap entri terbaca utuh', () => {
    const hasil = humanizeValue([
        { name: 'Denny Firmansyah', fields: ['email', 'jabatan'] },
        { name: 'Andi Pratama', fields: ['phone'] },
    ]);

    assert.equal(hasil.split('\n').length, 2);
});

test('objek tunggal dibaca sebagai pasangan label dan nilai', () => {
    const hasil = humanizeValue({ name: 'Denny Firmansyah', fields: ['email'] });

    assert.doesNotMatch(hasil, /\[object Object\]/);
    assert.match(hasil, /Name: Denny Firmansyah/);
    assert.match(hasil, /Fields: email/);
});

test('objek bersarang tetap terbaca sampai lapisan terdalam', () => {
    const hasil = humanizeValue({ sumber: { driver: 'mock', jumlah: 25 } });

    assert.doesNotMatch(hasil, /\[object Object\]/);
    assert.match(hasil, /Driver: mock/);
    assert.match(hasil, /Jumlah: 25/);
});
