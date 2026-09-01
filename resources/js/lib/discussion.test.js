/**
 * Run with: node --test resources/js/lib/discussion.test.js
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { isMine } from './discussion.js';

const pembaca = { id: 3844, name: 'Dummy SINTA 3' };

test('komentar sendiri dikenali dari id, bukan peran', () => {
    const komentar = { authorId: 3844, authorName: 'Dummy SINTA 3', authorRole: 'Support' };

    assert.equal(isMine(komentar, pembaca, 'Support'), true);
});

test('INTI: rekan seperan bukan diri sendiri', () => {
    // Support BPO dan Support IT sama-sama berlabel "Support".
    const dariRekan = { authorId: 1204, authorName: 'Dummy SINTA', authorRole: 'Support' };

    assert.equal(isMine(dariRekan, pembaca, 'Support'), false);
});

test('nama kembar tidak tertukar selama id tersedia', () => {
    const namaSama = { authorId: 999, authorName: 'Dummy SINTA 3', authorRole: 'Support' };

    assert.equal(isMine(namaSama, pembaca, 'Support'), false);
});

test('komentar lama tanpa id jatuh ke perbandingan nama', () => {
    const lama = { authorId: null, authorName: 'Dummy SINTA 3', authorRole: 'Support' };
    const lamaRekan = { authorId: null, authorName: 'Dummy SINTA', authorRole: 'Support' };

    assert.equal(isMine(lama, pembaca, 'Support'), true);
    assert.equal(isMine(lamaRekan, pembaca, 'Support'), false);
});

test('cadangan nama dibatasi pada peran yang sama', () => {
    const namaSamaPeranLain = { authorId: null, authorName: 'Dummy SINTA 3', authorRole: 'Requester' };

    assert.equal(isMine(namaSamaPeranLain, pembaca, 'Support'), false);
});

test('pesan sistem tidak pernah jadi milik siapa pun', () => {
    const sistem = { authorId: null, authorName: 'Helpdesk', authorRole: 'Sistem' };

    assert.equal(isMine(sistem, pembaca, 'Support'), false);
});

test('tanpa identitas pembaca, perilaku lama berbasis peran tetap berlaku', () => {
    const komentar = { authorId: 1204, authorName: 'Siapa pun', authorRole: 'Approver' };

    assert.equal(isMine(komentar, null, 'Approver'), true);
    assert.equal(isMine(komentar, null, 'Requester'), false);
});
