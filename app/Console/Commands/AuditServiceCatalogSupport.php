<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ServiceCatalogSubject;
use App\Models\SupportAgent;
use Illuminate\Console\Command;

/**
 * Laporan (bukan perbaikan otomatis): Subjek katalog yang PIC BPO/IT-nya
 * kosong, mengarah ke agent nonaktif, atau mengarah ke agent yang tipenya
 * tidak cocok — plus baris support_agents yang kelihatannya duplikat
 * (orang yang sama, baris berbeda).
 *
 * Dibuat sebagai laporan murni karena siapa PIC yang benar untuk tiap
 * Subjek adalah keputusan bisnis, bukan sesuatu yang bisa ditebak dari
 * data yang ada — hanya Admin yang tahu. Hasilnya dipakai untuk mengisi
 * ulang lewat UI Edit Layanan (yang sekarang dropdown-nya bisa dicari).
 */
class AuditServiceCatalogSupport extends Command
{
    protected $signature = 'catalog:audit-support';

    protected $description = 'Laporkan Subjek service catalog yang PIC BPO/IT-nya kosong/rusak, dan baris support_agents yang diduga duplikat';

    public function handle(): int
    {
        $agents = SupportAgent::all()->keyBy('id');

        $this->auditSubjects($agents);
        $this->newLine();
        $this->auditDuplicateAgents($agents);

        return self::SUCCESS;
    }

    private function auditSubjects($agents): void
    {
        $subjects = ServiceCatalogSubject::where('is_active', true)->orderBy('id')->get();

        $rows = [];

        foreach ($subjects as $s) {
            $bpo = $agents->get($s->support_agent_id);
            $it = $agents->get($s->it_agent_id);

            $masalah = [];

            if ((int) $s->support_level === 2) {
                if (! $s->support_agent_id) {
                    $masalah[] = 'Level 2 tapi Support BPO kosong';
                }
                if (! $s->it_agent_id) {
                    $masalah[] = 'Level 2 tapi Support IT kosong';
                }
            } elseif (! $s->support_agent_id && ! $s->it_agent_id) {
                $masalah[] = 'Level 1 tapi BPO & IT dua-duanya kosong';
            }

            if ($s->support_agent_id && ! $bpo) {
                $masalah[] = "Support BPO menunjuk id {$s->support_agent_id} yang sudah tidak ada";
            } elseif ($bpo && ! $bpo->is_active) {
                $masalah[] = "Support BPO ({$bpo->name}) sudah nonaktif";
            } elseif ($bpo && $bpo->type !== 'bpo') {
                $masalah[] = "Support BPO menunjuk agent tipe '{$bpo->type}', bukan 'bpo'";
            }

            if ($s->it_agent_id && ! $it) {
                $masalah[] = "Support IT menunjuk id {$s->it_agent_id} yang sudah tidak ada";
            } elseif ($it && ! $it->is_active) {
                $masalah[] = "Support IT ({$it->name}) sudah nonaktif";
            } elseif ($it && $it->type !== 'it') {
                $masalah[] = "Support IT menunjuk agent tipe '{$it->type}', bukan 'it'";
            }

            if ($masalah !== []) {
                $rows[] = [
                    $s->id,
                    $s->name,
                    (int) $s->support_level,
                    $bpo?->name ?? ($s->support_agent_id ? "id {$s->support_agent_id} (hilang)" : '—'),
                    $it?->name ?? ($s->it_agent_id ? "id {$s->it_agent_id} (hilang)" : '—'),
                    implode('; ', $masalah),
                ];
            }
        }

        if ($rows === []) {
            $this->info('Semua Subjek aktif punya PIC BPO/IT yang valid sesuai Level-nya.');

            return;
        }

        $this->warn(count($rows).' Subjek perlu dibetulkan PIC-nya lewat Edit Layanan:');
        $this->table(['ID', 'Subjek', 'Level', 'Support BPO', 'Support IT', 'Masalah'], $rows);
    }

    private function auditDuplicateAgents($agents): void
    {
        $groups = $agents->groupBy(fn (SupportAgent $a) => $a->type.'|'.mb_strtolower(trim($a->name)));

        $rows = [];

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            foreach ($group->sortByDesc(fn (SupportAgent $a) => $a->user_id !== null) as $a) {
                $rows[] = [
                    $a->id,
                    $a->name,
                    strtoupper($a->type),
                    $a->user_id ?? '—',
                    $a->is_active ? 'Aktif' : 'Nonaktif',
                ];
            }
        }

        if ($rows === []) {
            $this->info('Tidak ada baris support_agents yang kelihatan duplikat (nama sama, tipe sama).');

            return;
        }

        $this->warn(count($rows).' baris support_agents diduga duplikat (nama+tipe sama) — cek mana yang masih terhubung ke akun (kolom "User ID"):');
        $this->table(['ID', 'Nama', 'Tipe', 'User ID', 'Status'], $rows);
    }
}
