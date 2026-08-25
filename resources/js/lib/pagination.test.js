/**
 * Run with: node --test resources/js/lib/pagination.test.js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { PAGE_SIZE, clampPage, pageCount, pageRange, pageSlice } from './pagination.js';

const rows = Array.from({ length: 43 }, (_, i) => i + 1);

test('daftar kosong tetap punya satu halaman', () => {
    assert.equal(pageCount(0), 1);
    assert.deepEqual(pageSlice([], 1), []);
    assert.deepEqual(pageRange(1, 0), { from: 0, to: 0 });
});

test('menghitung jumlah halaman dari jumlah baris', () => {
    assert.equal(pageCount(43), 3);
    assert.equal(pageCount(PAGE_SIZE), 1);
    assert.equal(pageCount(PAGE_SIZE + 1), 2);
});

test('memotong baris sesuai halaman, halaman terakhir boleh lebih pendek', () => {
    assert.deepEqual(pageSlice(rows, 1).length, PAGE_SIZE);
    assert.equal(pageSlice(rows, 1)[0], 1);
    assert.equal(pageSlice(rows, 2)[0], PAGE_SIZE + 1);
    assert.equal(pageSlice(rows, 3).length, 43 - 2 * PAGE_SIZE);
    assert.equal(pageSlice(rows, 3).at(-1), 43);
});

test('nomor halaman di luar jangkauan ditarik ke batas terdekat', () => {
    assert.equal(clampPage(0, 43), 1);
    assert.equal(clampPage(-5, 43), 1);
    assert.equal(clampPage(99, 43), 3);
    assert.equal(clampPage(2, 43), 2);
});

test('halaman yang menyusut setelah penyaringan tidak menyisakan layar kosong', () => {
    // Pengguna di halaman 3, lalu memfilter sampai tersisa 4 baris.
    const halaman = clampPage(3, 4);

    assert.equal(halaman, 1);
    assert.deepEqual(pageSlice([1, 2, 3, 4], halaman), [1, 2, 3, 4]);
});

test('rentang untuk penghitung dihitung satu-basis', () => {
    assert.deepEqual(pageRange(1, 43), { from: 1, to: PAGE_SIZE });
    assert.deepEqual(pageRange(3, 43), { from: 2 * PAGE_SIZE + 1, to: 43 });
    assert.deepEqual(pageRange(1, 7), { from: 1, to: 7 });
});
