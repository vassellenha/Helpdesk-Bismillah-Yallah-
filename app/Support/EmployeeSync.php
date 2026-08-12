<?php

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use App\Support\EmployeeDirectory\EmployeeDirectory;
use App\Support\EmployeeDirectory\HttpEmployeeDirectory;
use App\Support\EmployeeDirectory\MockEmployeeDirectory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pulls employee master data from the company directory into the users table.
 *
 * Deliberately conservative — the helpdesk is not the system of record for
 * people, but it is the system of record for who can log a ticket, so the sync
 * never destroys local state it did not create:
 *
 *  - matches on NIP (configurable), never on the mutable email
 *  - only writes columns present in the payload; unmapped ones (unit, roles,
 *    password) are left exactly as the Admin set them
 *  - creates accounts with the default role only; never re-assigns roles
 *  - deactivates absent employees only when explicitly enabled in config
 */
class EmployeeSync
{
    /** Keeps the audit payload bounded on a full company-sized directory. */
    private const MAX_REPORTED_CHANGES = 25;

    /**
     * @return array{fetched:int,created:int,updated:int,unchanged:int,deactivated:int,kept_empty:int,kept_admin_override:int,changes:array<int,array{name:string,fields:array<int,string>}>,skipped:array<int,string>,dry_run:bool}
     */
    public static function run(bool $dryRun = false): array
    {
        // A full company directory can take minutes to fetch and hash — well
        // past the default request time budget. CLI (the scheduled sync) is
        // unaffected, but the three HTTP-triggered call sites (User & Role
        // Management's "Sync Data Pegawai" button, and Integrasi's test/sync)
        // inherit PHP's max_execution_time and were dying mid-run: the fetch
        // alone ate most of the 120s, then Hash::make() for each new account
        // pushed it over, killing the request before recordAudit() ever ran.
        set_time_limit(0);

        $config = config('integrations.employee_directory');
        $matchBy = $config['match_by'] ?? 'nip';
        $fallbackBy = $config['fallback_match_by'] ?? null;
        $overwriteWithEmpty = (bool) ($config['overwrite_with_empty'] ?? false);

        $rows = self::directory()->fetch();

        $summary = [
            'fetched' => count($rows),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deactivated' => 0,
            // Mapped fields the API sent empty while local data had a value, so
            // the local value was kept. Without this, an Admin edit that the sync
            // declines to overwrite looks identical to "nothing to do".
            'kept_empty' => 0,
            // Mapped fields an Admin manually edited (admin_overridden_fields) —
            // held back even though the API sent a real, different value, same
            // visibility reasoning as kept_empty above.
            'kept_admin_override' => 0,
            // Local accounts the API never mentioned — hand-made by an Admin, or
            // an employee the feed no longer returns. They are left completely
            // alone (unless deactivate_missing is on), which is indistinguishable
            // from "nothing to do" unless it is counted.
            'not_in_source' => [],
            // Employees found only via the fallback key because the API's
            // match_by value disagrees with ours. They still sync; the key
            // itself is left alone and listed here for a human to settle.
            'key_mismatch' => [],
            'changes' => [],
            'skipped' => [],
            'dry_run' => $dryRun,
        ];

        $seen = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $summary['skipped'][] = "Baris #{$index}: bukan objek.";

                continue;
            }

            $attrs = self::mapRow($row, $config);
            $key = $attrs[$matchBy] ?? null;

            if (blank($key)) {
                $label = $attrs['name'] ?? "baris #{$index}";
                $summary['skipped'][] = "{$label}: kolom pencocok \"{$matchBy}\" kosong.";

                continue;
            }

            $user = User::where($matchBy, $key)->first();

            // Second chance on the fallback key. Without this a single digit of
            // NIP drift turns an existing employee into a "new" one, which then
            // dies on the email unique index — the whole feed runs and updates
            // nobody, which is exactly what a real API mismatch looks like.
            if (! $user && $fallbackBy && ! blank($attrs[$fallbackBy] ?? null)) {
                $user = User::where($fallbackBy, $attrs[$fallbackBy])->first();

                if ($user) {
                    $summary['key_mismatch'][] = sprintf(
                        '%s: %s di API "%s", di helpdesk "%s" — dicocokkan lewat %s, %s tidak diubah.',
                        $user->name, $matchBy, $key, $user->{$matchBy}, $fallbackBy, $matchBy
                    );

                    // Never rewrite the identity we match on. Everything else keys
                    // off it — CurrentActor's personas included — so a rewrite
                    // would break lookups elsewhere and leave the next sync unable
                    // to find this person at all. Report it; let a human settle it.
                    unset($attrs[$matchBy]);
                }
            }

            // Track the local key when we matched an existing account, so an
            // employee reached via fallback is not also reported as "not in source".
            $seen[] = $user ? $user->{$matchBy} : $key;

            // A genuinely different person already owns this email — writing it
            // would break the unique index, so surface it instead of failing hard.
            if (! blank($attrs['email'] ?? null)) {
                $emailOwner = User::where('email', $attrs['email'])
                    ->when($user, fn ($q) => $q->whereKeyNot($user->getKey()))
                    ->first();

                if ($emailOwner) {
                    $summary['skipped'][] = "{$attrs['name']} ({$key}): email {$attrs['email']} sudah dipakai {$emailOwner->name}.";

                    continue;
                }
            }

            if ($user === null) {
                $summary['created']++;
                self::report($summary, $attrs['name'] ?? (string) $key, ['(akun baru)']);

                if (! $dryRun) {
                    self::createUser($attrs, $config);
                }

                continue;
            }

            [$changed, $keptEmpty, $keptOverride] = self::diffRow($user, $attrs, $overwriteWithEmpty);
            $summary['kept_empty'] += count($keptEmpty);
            $summary['kept_admin_override'] += count($keptOverride);

            /*
             | synced_at ditulis untuk SETIAP orang yang ditemukan di sumber,
             | termasuk yang datanya tidak berubah sama sekali.
             |
             | Yang dicatat bukan "kapan barisnya terakhir diubah" — itu sudah
             | tugas updated_at — melainkan "kapan orang ini terakhir terlihat
             | di direktori perusahaan". Pegawai yang datanya stabil bertahun-tahun
             | tetap pegawai sungguhan, dan kalau kolom ini hanya diisi saat ada
             | perubahan, justru merekalah yang akan tampak seperti akun lokal
             | sisa uji coba.
             */
            if (! $dryRun) {
                $user->synced_at = now();
            }

            if ($changed === []) {
                $summary['unchanged']++;

                if (! $dryRun) {
                    $user->save();
                }

                continue;
            }

            $summary['updated']++;
            self::report($summary, $user->name, array_keys($changed));

            if (! $dryRun) {
                $user->fill($changed)->save();
            }
        }

        if ($summary['fetched'] > 0) {
            $summary['not_in_source'] = self::localOnly($matchBy, $seen);

            if ($config['deactivate_missing'] ?? false) {
                $summary['deactivated'] = self::deactivateMissing($matchBy, $seen, $dryRun);
            }
        }

        if (! $dryRun) {
            self::recordAudit($summary);
        }

        return $summary;
    }

    /**
     * Translate one payload row into users columns using the configured map.
     *
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $config
     * @return array<string,mixed>
     */
    private static function mapRow(array $row, array $config): array
    {
        $attrs = [];

        foreach ($config['field_map'] ?? [] as $source => $column) {
            if (! array_key_exists($source, $row)) {
                continue;
            }

            $value = $row[$source];
            $attrs[$column] = is_string($value) ? trim($value) : $value;
        }

        /*
         | Alamat kosong disimpan sebagai NULL, bukan "".
         |
         | `users.email` unique: string kosong hanya boleh ada SATU baris, jadi
         | menuliskan "" akan meloloskan pegawai pertama tanpa email lalu
         | menabrakkan 1.277 sisanya ke unique index — mematikan sync di tengah
         | jalan setelah sebagian orang sudah dibuat. NULL boleh berulang.
         |
         | Diletakkan di sini, bukan di createUser(), supaya diffRow() ikut
         | melihat nilai yang sama: dengan overwrite_with_empty = false, NULL
         | dianggap blank dan email yang sudah ada di helpdesk tidak akan
         | terhapus hanya karena API tidak mengirimkannya.
         */
        if (array_key_exists('email', $attrs) && blank($attrs['email'])) {
            $attrs['email'] = null;
        }

        if (isset($attrs['status'])) {
            $raw = strtoupper((string) $attrs['status']);
            $mapped = $config['status_map'][$raw] ?? null;

            if ($mapped === null) {
                // An unrecognised code must never decide someone's access: guessing
                // "active" would silently reinstate a resigned employee, guessing
                // "inactive" would lock out a working one. Leave status untouched
                // and log the code so status_map can be extended.
                Log::warning('[EmployeeSync] Kode status pegawai tidak dikenal — status tidak diubah.', [
                    'nip' => $attrs['nip'] ?? null,
                    'status' => $raw,
                ]);
                unset($attrs['status']);
            } else {
                $attrs['status'] = $mapped;
            }
        }

        // Same rule the Admin form uses: username defaults to the corporate email.
        if (blank($attrs['username'] ?? null) && ! blank($attrs['email'] ?? null)) {
            $attrs['username'] = $attrs['email'];
        }

        return $attrs;
    }

    /**
     * Records which fields a run touched for one person, capped so a full
     * company directory cannot bloat the audit row.
     *
     * @param  array<string,mixed>  $summary
     * @param  array<int,string>  $fields
     */
    private static function report(array &$summary, string $name, array $fields): void
    {
        if (count($summary['changes']) >= self::MAX_REPORTED_CHANGES) {
            return;
        }

        $summary['changes'][] = ['name' => $name, 'fields' => $fields];
    }

    /**
     * Splits a row into what actually differs and what was deliberately left
     * alone, so an unchanged employee never bumps updated_at and — just as
     * important — the caller can explain a "0 diperbarui" run.
     *
     * A mapped field the API sent empty is held back by default: clearing real
     * data because a payload omitted it is worse than being slightly stale. That
     * makes such a field non-authoritative for this row, which is invisible
     * unless reported, so it is counted separately instead of silently skipped.
     *
     * A field an Admin has manually edited (users.admin_overridden_fields) is
     * held back the same way, even when the API sends a real, different
     * value — an Admin correction must survive the next sync, not just the
     * next payload that happens to omit the field.
     *
     * @param  array<string,mixed>  $attrs
     * @return array{0:array<string,mixed>,1:array<int,string>,2:array<int,string>} [changed, keptEmpty, keptOverride]
     */
    private static function diffRow(User $user, array $attrs, bool $overwriteWithEmpty): array
    {
        $changed = [];
        $keptEmpty = [];
        $keptOverride = [];
        $overridden = $user->admin_overridden_fields ?? [];

        foreach ($attrs as $column => $value) {
            if (in_array($column, $overridden, true)) {
                if ((string) $user->{$column} !== (string) $value) {
                    $keptOverride[] = $column;
                }

                continue;
            }

            if (($value === null || $value === '') && ! $overwriteWithEmpty) {
                // Only worth reporting when we actually held something back.
                if (! blank($user->{$column})) {
                    $keptEmpty[] = $column;
                }

                continue;
            }

            if ((string) $user->{$column} !== (string) $value) {
                $changed[$column] = $value;
            }
        }

        return [$changed, $keptEmpty, $keptOverride];
    }

    /**
     * @param  array<string,mixed>  $attrs
     * @param  array<string,mixed>  $config
     */
    private static function createUser(array $attrs, array $config): void
    {
        DB::transaction(function () use ($attrs, $config) {
            $status = $attrs['status'] ?? 'active';

            $user = User::create([
                ...$attrs,
                'status' => $status,
                // A brand-new account has no Admin decision to protect yet, so
                // this is the one place helpdesk_access may follow the company
                // API instead of defaulting to 'enabled': an employee who
                // arrives already inactive would otherwise sit at the schema
                // default with access nobody actually granted, and the User &
                // Role Management row action menu would misleadingly offer
                // "Nonaktifkan Akses" — as if turning it off were still
                // pending — for someone the Admin never activated in the
                // first place.
                'helpdesk_access' => $status === 'active' ? 'enabled' : 'disabled',
                // No local password: these accounts authenticate against the
                // company portal. A random one keeps the column non-null.
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
                // Menandai akun ini berasal dari direktori perusahaan, bukan
                // diketik Admin atau ditinggalkan seeder — lihat migrasi
                // 2026_08_10_130000.
                'synced_at' => now(),
            ]);

            $role = Role::where('name', $config['default_role'] ?? 'Requester')->first();

            if ($role) {
                $user->roles()->attach($role->id);
            }
        });
    }

    /**
     * Accounts that exist here but were absent from the API response. Reported
     * so an Admin-created account (or an employee the feed dropped) is never
     * silently passed over — the sync leaving them untouched is correct, but it
     * must be visible.
     *
     * @param  array<int,mixed>  $seen
     * @return array<int,string>
     */
    private static function localOnly(string $matchBy, array $seen): array
    {
        return User::whereNotNull($matchBy)
            ->where($matchBy, '!=', '')
            ->whereNotIn($matchBy, $seen)
            ->orderBy('name')
            ->pluck('name')
            ->take(self::MAX_REPORTED_CHANGES)
            ->all();
    }

    /**
     * @param  array<int,mixed>  $seen
     */
    private static function deactivateMissing(string $matchBy, array $seen, bool $dryRun): int
    {
        $query = User::where('status', 'active')
            ->whereNotNull($matchBy)
            ->where($matchBy, '!=', '')
            ->whereNotIn($matchBy, $seen);

        if ($dryRun) {
            return $query->count();
        }

        return $query->update(['status' => 'inactive']);
    }

    /**
     * @param  array<string,mixed>  $summary
     */
    private static function recordAudit(array $summary): void
    {
        $actor = self::auditActor();

        if (! $actor) {
            // Tidak ada Administrator sama sekali di basis data — hanya mungkin
            // pada instalasi yang belum di-seed. Baris audit dilewati, tapi
            // TIDAK diam-diam: sinkronisasinya sendiri sudah berjalan dan
            // hasilnya harus tetap bisa ditelusuri di log.
            Log::warning('[EmployeeSync] Audit trail dilewati: tidak ada akun ber-role Administrator untuk diatribusikan.', $summary);

            return;
        }

        $skipped = count($summary['skipped']);

        // A run that fetched nothing must not read like a successful no-op —
        // "0 diterima, 0 diperbarui" is indistinguishable from "already in sync".
        $description = $summary['fetched'] === 0
            ? 'Sinkronisasi data pegawai GAGAL: tidak ada data diterima dari sumber.'
            : sprintf(
                'Sinkronisasi data pegawai: %d diterima, %d dibuat, %d diperbarui, %d tetap, %d dilewati, %d field dipertahankan (API kosong), %d field dipertahankan (override Admin), %d di luar sumber, %d kunci tidak cocok.',
                $summary['fetched'],
                $summary['created'],
                $summary['updated'],
                $summary['unchanged'],
                $skipped,
                $summary['kept_empty'],
                $summary['kept_admin_override'],
                count($summary['not_in_source']),
                count($summary['key_mismatch']),
            );

        AuditTrail::record($actor, [
            'module' => 'integration',
            'action' => 'sync',
            'target_type' => 'user',
            'target_name' => 'Employee Directory',
            'new_value' => $summary,
            'description' => $description,
        ]);
    }

    /**
     * Siapa yang dicatat sebagai pelaku sinkronisasi.
     *
     * DUA jalur, dan itulah sebabnya ini tidak bisa sekadar
     * `CurrentActor::admin()` seperti dulu. Sinkronisasi dijalankan lewat tombol
     * di konsol Admin, TAPI juga oleh penjadwal (lihat routes/console.php) —
     * dan di jalur penjadwal tidak ada siapa pun yang login. Sejak persona tetap
     * dicabut, `CurrentActor::admin()` di sana menolak dengan 401 dan
     * menggagalkan sinkronisasi terjadwal yang sebenarnya sudah selesai bekerja.
     *
     * Jadi: Admin yang menekan tombolnya kalau memang ada, selain itu
     * Administrator pertama sebagai atribusi sistem. Diurutkan berdasarkan id
     * supaya dua jalannya penjadwal tidak tercatat atas nama dua orang berbeda.
     */
    private static function auditActor(): ?User
    {
        $user = CurrentActor::user();

        if ($user?->roles->contains('name', 'Administrator')) {
            return $user;
        }

        return User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))
            ->orderBy('id')
            ->first();
    }

    /**
     * Resolves the active directory from config — swap the driver in
     * config/integrations.php (or .env EMPLOYEE_DIRECTORY_DRIVER).
     */
    private static function directory(): EmployeeDirectory
    {
        $config = config('integrations.employee_directory');

        return match ($config['driver'] ?? 'mock') {
            'http' => new HttpEmployeeDirectory(
                $config['http']['base_url'] ?? null,
                $config['http']['token'] ?? null,
                $config['http']['endpoint'] ?? '/api/v1/employees',
                (int) ($config['http']['timeout'] ?? 30),
                (string) ($config['http']['collection_key'] ?? 'data'),
            ),
            default => new MockEmployeeDirectory($config['mock']['fixture'] ?? ''),
        };
    }
}
