/**
 * Run with: node --test resources/js/lib/i18n.test.js
 *
 * No test runner is installed in this project, so these use Node's built-in
 * test module. `t()` reads the dictionary from `window.__LANG__` at call time,
 * which lets each test swap the dictionary without reimporting the module.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { t } from './i18n.js';

function withMessages(messages) {
    globalThis.window = { __LANG__: messages };
}

test('mengganti placeholder biasa', () => {
    withMessages({ support: { dashboard: { showing: 'Menampilkan :shown dari :total tiket' } } });

    assert.equal(t('support.dashboard.showing', { shown: 5, total: 20 }), 'Menampilkan 5 dari 20 tiket');
});

test('placeholder yang namanya awalan dari placeholder lain tidak saling memakan', () => {
    withMessages({ admin: { audit: { showing: 'Menampilkan :from–:to dari :total aktivitas' } } });

    assert.equal(
        t('admin.audit.showing', { from: 1, to: 15, total: 192 }),
        'Menampilkan 1–15 dari 192 aktivitas',
    );
});

test('urutan properti pada objek pengganti tidak mengubah hasil', () => {
    withMessages({ teamlead: { monitoring: { showing: ':from–:to dari :total tiket' } } });

    assert.equal(
        t('teamlead.monitoring.showing', { total: 192, to: 15, from: 1 }),
        '1–15 dari 192 tiket',
    );
});

test('kunci yang tidak ada mengembalikan kuncinya sendiri, atau fallback bila diberikan', () => {
    withMessages({});

    assert.equal(t('admin.tidak.ada'), 'admin.tidak.ada');
    assert.equal(t('admin.tidak.ada', {}, 'Peran Kustom'), 'Peran Kustom');
});
