<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Tests\TestCase;

/**
 * Sinkronisasi data pegawai terjadwal.
 *
 * Yang dijaga di sini bukan "apakah sinkronisasinya benar" — itu milik
 * EmployeeSyncTest — melainkan APAKAH IA JALAN SENDIRI, dan apakah saklarnya
 * benar-benar mematikan.
 *
 * Saklarnya penting justru karena sinkronisasi ini MENIMPA data user, termasuk
 * kolom `status` yang mengunci akses. Sinkronisasi yang menyala di lingkungan
 * yang sumbernya masih fixture contoh akan menonaktifkan orang-orang sungguhan
 * setiap malam, dan tidak ada yang menyadarinya sampai ada yang mengeluh tidak
 * bisa masuk.
 */
final class EmployeeSyncScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_terjadwal_harian_saat_saklarnya_menyala(): void
    {
        Config::set('integrations.employee_directory.auto_sync.enabled', true);
        Config::set('integrations.employee_directory.auto_sync.at', '03:00');

        $this->assertSame('0 3 * * *', $this->cronOf('employees:sync'));
    }

    public function test_tidak_terjadwal_saat_saklarnya_mati(): void
    {
        Config::set('integrations.employee_directory.auto_sync.enabled', false);

        $this->assertNull($this->cronOf('employees:sync'));
    }

    public function test_jam_jalannya_mengikuti_config(): void
    {
        Config::set('integrations.employee_directory.auto_sync.enabled', true);
        Config::set('integrations.employee_directory.auto_sync.at', '23:30');

        $this->assertSame('30 23 * * *', $this->cronOf('employees:sync'));
    }

    /**
     * Ekspresi cron perintah tersebut, atau null kalau tidak terdaftar.
     *
     * Jadwalnya dibaca ulang dari berkas rutenya, bukan dari Schedule yang
     * sudah terlanjur dibangun saat aplikasi boot — pada saat itu config yang
     * dipakai masih nilai bawaan, jadi Config::set() di atas tidak akan
     * berpengaruh apa pun.
     */
    private function cronOf(string $command): ?string
    {
        $schedule = new Schedule;
        $this->app->instance(Schedule::class, $schedule);

        // Facade menyimpan instans yang sudah pernah ia selesaikan. Tanpa baris
        // ini, Schedule::command() di berkas rute menulis ke jadwal LAMA yang
        // dibangun saat aplikasi boot — dan pemeriksaan di bawah selalu kosong,
        // berapa pun nilai config-nya.
        ScheduleFacade::clearResolvedInstances();

        require base_path('routes/console.php');

        foreach ($schedule->events() as $event) {
            if (str_contains($event->command ?? '', $command)) {
                return $event->expression;
            }
        }

        return null;
    }
}
