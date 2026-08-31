<?php

namespace App\Console\Commands;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use App\Support\SupportAgentSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One-off import of the "Export Users" CSV (User & Role Management's own
 * export button — see UserRoleController::export()) back into a *different*
 * environment's `users` table.
 *
 * This exists because local dev's employee data had drifted from the real
 * server: local was seeded/synced at an earlier point and never caught up,
 * so searches and filters that work fine against the real ~3.8k-employee
 * directory returned near-empty results locally. Re-running employees:sync
 * isn't an option here — this environment's driver is "mock" — so instead
 * we replay a real export captured from the server.
 *
 * Deliberately conservative, same spirit as EmployeeSync:
 *  - matches on NIP (the export's "NPP" column) — the same key employees:sync
 *    uses, so a person who already exists locally (e.g. seeded test
 *    accounts) is updated in place, never duplicated.
 *  - rows with no NPP ("-") are skipped — there is no reliable key to match
 *    or dedupe them on.
 *  - fields an Admin already hand-edited (users.admin_overridden_fields) are
 *    left alone, exactly like employees:sync.
 *  - roles are only ADDED, never removed — the export's Role column reflects
 *    real admin decisions on the server and should enrich local test
 *    accounts, but this command must never quietly strip a role a local
 *    tester relies on.
 */
class ImportEmployeeCsvExport extends Command
{
    protected $signature = 'employees:import-csv {path} {--apply : Write changes; without this flag nothing is saved}';

    protected $description = 'Import a "User & Role Management -> Export" CSV into the users table (dry-run by default)';

    private const STATUS_MAP = [
        'Aktif' => 'active',
        'Nonaktif' => 'inactive',
    ];

    public function handle(): int
    {
        $path = $this->argument('path');
        $apply = (bool) $this->option('apply');

        // Same reasoning as UserRoleController::importCsv() (the UI button
        // this command predates): a raw CSV bulk-edit has no legitimate job
        // outside local dev, where employees:sync already keeps real
        // environments fresh from the live API. --apply is refused
        // everywhere else; a --dry-run preview is harmless and stays open.
        if ($apply && ! app()->environment('local')) {
            $this->error('--apply hanya diizinkan di environment local. Jalankan tanpa --apply untuk pratinjau.');

            return self::FAILURE;
        }

        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        // Strip the UTF-8 BOM the export writes so the "Nama" header matches.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        $header = array_map('trim', $header);

        $summary = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped_no_nip' => 0,
            'skipped_email_collision' => [],
            'roles_added' => 0,
            'changes' => [],
        ];

        $roleCache = Role::pluck('id', 'name');
        $seenNip = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }
            $r = array_combine($header, array_slice($row, 0, count($header)));

            $nip = trim((string) ($r['NPP'] ?? ''));
            if ($nip === '' || $nip === '-') {
                $summary['skipped_no_nip']++;

                continue;
            }

            // Same NIP already processed earlier in the file (dupes happen in
            // real directories) — first row wins, rest are left alone.
            if (isset($seenNip[$nip])) {
                continue;
            }
            $seenNip[$nip] = true;

            $email = trim((string) ($r['Email'] ?? ''));
            $email = ($email === '' || $email === '-') ? null : $email;

            $phone = trim((string) ($r['Telepon'] ?? ''));
            $phone = ($phone === '' || $phone === '-') ? null : $phone;

            $jabatan = trim((string) ($r['Jabatan'] ?? ''));
            $jabatan = ($jabatan === '' || $jabatan === '-') ? null : $jabatan;

            $unit = trim((string) ($r['Unit Kerja'] ?? ''));
            $unit = $unit === '' ? null : $unit;

            $statusRaw = trim((string) ($r['Status'] ?? ''));
            $status = self::STATUS_MAP[$statusRaw] ?? null;

            $lastLoginRaw = trim((string) ($r['Terakhir Login'] ?? ''));
            $lastLogin = ($lastLoginRaw === '' || $lastLoginRaw === '-') ? null : $lastLoginRaw;

            $roleNames = array_filter(array_map('trim', explode(',', (string) ($r['Role'] ?? ''))));

            if ($email !== null) {
                $emailOwner = User::where('email', $email)->where('nip', '!=', $nip)->first();
                if ($emailOwner) {
                    $summary['skipped_email_collision'][] = "{$r['Nama']} ({$nip}): email {$email} sudah dipakai {$emailOwner->name} ({$emailOwner->nip}).";

                    continue;
                }
            }

            $user = User::where('nip', $nip)->first();
            $overridden = $user?->admin_overridden_fields ?? [];

            $attrs = array_filter([
                'name' => trim((string) $r['Nama']),
                'email' => $email,
                'phone' => $phone,
                'unit' => $unit,
                'jabatan' => $jabatan,
                'status' => $status,
            ], fn ($v) => $v !== null);

            if ($lastLogin !== null) {
                $attrs['last_login_at'] = $lastLogin;
            }

            if ($user === null) {
                $summary['created']++;
                $summary['changes'][] = "BARU: {$attrs['name']} ({$nip})";

                if ($apply) {
                    $newUser = User::create([
                        ...$attrs,
                        'nip' => $nip,
                        'status' => $status ?? 'active',
                        'helpdesk_access' => ($status ?? 'active') === 'active' ? 'enabled' : 'disabled',
                        'password' => Hash::make(Str::random(40)),
                        'email_verified_at' => now(),
                        'synced_at' => now(),
                    ]);

                    $this->attachRoles($newUser, $roleNames, $roleCache, $summary);
                }

                continue;
            }

            $changed = [];
            foreach ($attrs as $col => $value) {
                if (in_array($col, $overridden, true)) {
                    continue;
                }
                if ((string) $user->{$col} !== (string) $value) {
                    $changed[$col] = $value;
                }
            }

            if ($changed !== []) {
                $summary['updated']++;
                $summary['changes'][] = "{$user->name} ({$nip}): ".implode(', ', array_keys($changed));
                $summary['updates'][] = "{$user->name} ({$nip}): ".implode(', ', array_keys($changed));

                if ($apply) {
                    $user->fill($changed)->save();
                }
            } else {
                $summary['unchanged']++;
            }

            if ($apply) {
                $this->attachRoles($user, $roleNames, $roleCache, $summary);
            } else {
                $missing = collect($roleNames)->reject(fn ($n) => $user->roles->contains('name', $n));
                $summary['roles_added'] += $missing->count();
            }
        }

        fclose($handle);

        $this->newLine();
        $this->table(['Hasil', 'Jumlah'], [
            ['Dibuat', $summary['created']],
            ['Diperbarui', $summary['updated']],
            ['Tidak berubah', $summary['unchanged']],
            ['Role ditambahkan', $summary['roles_added']],
            ['Dilewati (tanpa NPP)', $summary['skipped_no_nip']],
            ['Dilewati (email bentrok)', count($summary['skipped_email_collision'])],
        ]);

        $this->newLine();
        $this->line('<fg=yellow>Akun yang sudah ada, field yang berubah:</>');
        foreach ($summary['updates'] ?? [] as $line) {
            $this->line("  <fg=cyan>diubah</> {$line}");
        }

        foreach ($summary['skipped_email_collision'] as $line) {
            $this->warn("  {$line}");
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('Dry run selesai. Jalankan dengan --apply untuk menerapkan.');
        } elseif ($actor = $this->auditActor()) {
            AuditTrail::record($actor, [
                'module' => 'integration',
                'action' => 'sync',
                'target_type' => 'user',
                'target_name' => 'Employee CSV Import',
                'new_value' => array_diff_key($summary, ['changes' => 1]),
                'description' => sprintf(
                    'Impor CSV pegawai: %d dibuat, %d diperbarui, %d tetap, %d role ditambahkan, %d dilewati.',
                    $summary['created'], $summary['updated'], $summary['unchanged'], $summary['roles_added'], $summary['skipped_no_nip'] + count($summary['skipped_email_collision']),
                ),
            ]);
        }

        return self::SUCCESS;
    }

    /** @param array<int,string> $roleNames */
    private function attachRoles(User $user, array $roleNames, \Illuminate\Support\Collection $roleCache, array &$summary): void
    {
        $user->loadMissing('roles');
        $attached = false;

        foreach ($roleNames as $name) {
            $roleId = $roleCache[$name] ?? null;
            if (! $roleId) {
                continue;
            }
            if (! $user->roles->contains('id', $roleId)) {
                $user->roles()->attach($roleId);
                $summary['roles_added']++;
                $attached = true;
            }
        }

        if (! $attached) {
            return;
        }

        /*
         | Role Support tanpa baris support_agents adalah kegagalan yang tidak
         | menunjuk ke mana pun: orangnya tidak muncul di dropdown PIC Service
         | Catalog, dan dashboard Support-nya menjawab 404 — persis alasan
         | SupportAgentSync ditulis. UserRoleController sudah memanggilnya pada
         | dua jalurnya; importer ini luput karena membaca kolom "Role" dari CSV
         | apa adanya, jadi satu baris berisi "Support IT" membuka lubang yang
         | sama lewat pintu lain.
         |
         | Relasi WAJIB dimuat ulang lebih dulu: yang ada di memori adalah
         | daftar SEBELUM attach di atas, dan reconcile() memakai loadMissing()
         | yang tidak akan menyegarkannya sendiri — ia akan membaca role lama
         | dan menyimpulkan orang ini bukan Support.
        */
        $user->load('roles');
        SupportAgentSync::reconcile($user);
    }

    private function auditActor(): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))->orderBy('id')->first();
    }
}
