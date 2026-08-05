<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Sso\SsoEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Builds one signed entry link, the way ADHI's portal will.
 *
 * Two jobs: let us test /auth/sso/entry end to end before the portal exists,
 * and give ADHI's developer a known-good link to compare their own output
 * against. It signs through SsoEntry, so a link this prints is a link the
 * endpoint accepts — the two cannot disagree.
 */
class MakeSsoEntryLink extends Command
{
    protected $signature = 'sso:entry-link
        {email : Email pegawai, harus sama persis dengan kolom email di tabel users}
        {--base= : Base URL aplikasi (default: APP_URL)}
        {--ttl= : Umur tautan dalam detik (default: config sso.entry.ttl)}';

    protected $description = 'Buat satu tautan masuk SSO bertanda tangan untuk pengujian';

    public function handle(): int
    {
        $secret = (string) config('integrations.sso.entry.secret');

        if ($secret === '') {
            $this->error('SSO_ENTRY_SECRET masih kosong di .env — tautan tidak bisa ditandatangani.');
            $this->line('Isi dulu rahasia bersama dari ADHI, lalu jalankan: php artisan config:clear');

            return self::FAILURE;
        }

        if (SsoEntry::driver() !== 'hmac') {
            $this->warn('SSO_ENTRY_DRIVER saat ini "'.SsoEntry::driver().'" — endpoint /auth/sso/entry akan menjawab 404.');
            $this->line('Set SSO_ENTRY_DRIVER=hmac untuk mengaktifkannya.');
            $this->newLine();
        }

        $email = (string) $this->argument('email');
        $ttl = (int) ($this->option('ttl') ?: config('integrations.sso.entry.ttl', 120));

        // Checked here rather than left to the endpoint: "link works but login
        // is refused" and "link itself is malformed" look identical from the
        // browser, and that ambiguity is what makes these integrations drag.
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->warn("Tidak ada user dengan email {$email} di helpdesk.");
            $this->line('Tautan tetap dibuat, tapi endpoint akan menolaknya. Jalankan sinkronisasi data pegawai dulu.');
        } elseif (! $user->isActive()) {
            $this->warn("User {$user->name} ada, tapi TIDAK aktif: ".strtolower((string) $user->inactiveReason()).'.');
            $this->line('Tautan tetap dibuat, tapi endpoint akan menolaknya.');
        }

        $params = [
            'email' => $email,
            'ts' => time(),
            'nonce' => Str::random(24),
        ];
        $params['sig'] = SsoEntry::sign($params, $secret);

        $base = rtrim((string) ($this->option('base') ?: config('app.url')), '/');
        $link = $base.'/auth/sso/entry?'.http_build_query($params);

        $this->newLine();
        $this->line('<fg=gray>String yang ditandatangani:</>');
        $this->line('  '.SsoEntry::canonical($params));
        $this->newLine();
        $this->line('<fg=gray>Tautan (berlaku '.$ttl.' detik, sekali pakai):</>');
        $this->line('<fg=green>'.$link.'</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
