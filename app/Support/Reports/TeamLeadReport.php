<?php

namespace App\Support\Reports;

use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Isi laporan Reporting Team Lead: judul, kolom, dan baris — dihitung dari
 * tiket sungguhan.
 *
 * Satu-satunya sumber untuk pratinjau di layar maupun berkas yang diunduh
 * (PDF/Excel), supaya keduanya tidak pernah berbeda. Kelas ini sengaja tidak
 * tahu apa-apa soal HTTP: penyaring periode/unit dikerjakan pemanggilnya, lalu
 * koleksi tiket yang sudah tersaring dioper ke sini.
 */
class TeamLeadReport
{
    public const TYPES = [
        'sla_compliance' => 'SLA Compliance',
        'sla_breach' => 'SLA Breach',
        'support_perf' => 'Support Performance',
        'ticket_summary' => 'Ticket Summary',
        'top_incident' => 'Top Incident',
    ];

    /** Baris teratas yang ditampilkan laporan Top Incident. */
    private const TOP_INCIDENT_LIMIT = 10;

    /**
     * @param  array<int,array<string,mixed>>  $agentOptions  keluaran TeamLeadController::agentOptions()
     * @return array{title:string,columns:array<int,array{label:string,align:string}>,rows:array<int,array<int,string>>}
     */
    public static function build(string $type, Collection $tickets, array $agentOptions): array
    {
        return match ($type) {
            'sla_breach' => self::slaBreach($tickets),
            'support_perf' => self::supportPerformance($agentOptions),
            'ticket_summary' => self::ticketSummary($tickets),
            'top_incident' => self::topIncident($tickets),
            default => self::slaCompliance($tickets),
        };
    }

    /** @return array{title:string,columns:array<int,array{label:string,align:string}>,rows:array<int,array<int,string>>} */
    private static function slaBreach(Collection $tickets): array
    {
        return [
            'title' => 'SLA Breach Report',
            'columns' => [
                self::col(__('teamlead.report_cols.ticket')),
                self::col(__('teamlead.report_cols.subject')),
                self::col(__('teamlead.report_cols.app')),
                self::col(__('teamlead.report_cols.priority')),
                self::col(__('teamlead.report_cols.overdue'), 'right'),
            ],
            'rows' => $tickets->whereIn('status', Ticket::ACTIVE_STATUSES)
                ->filter(fn (Ticket $t) => $t->sla_kind === 'breach')
                ->sortBy('sla_minutes_remaining')
                ->map(fn (Ticket $t) => [
                    $t->ticket_no,
                    $t->subject_name ?? $t->title,
                    $t->service_name ?? '—',
                    $t->priority,
                    $t->sla_label,
                ])
                ->values()->all(),
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $agentOptions
     * @return array{title:string,columns:array<int,array{label:string,align:string}>,rows:array<int,array<int,string>>}
     */
    private static function supportPerformance(array $agentOptions): array
    {
        return [
            'title' => 'Support Performance Report',
            'columns' => [
                self::col(__('teamlead.report_cols.agent')),
                self::col(__('teamlead.report_cols.active_load'), 'right'),
                self::col(__('teamlead.report_cols.done'), 'right'),
                self::col(__('teamlead.report_cols.avg_resolution'), 'right'),
                self::col(__('teamlead.report_cols.sla'), 'right'),
            ],
            'rows' => collect($agentOptions)
                ->map(fn (array $a) => [
                    $a['name'],
                    (string) $a['load'],
                    (string) $a['resolved'],
                    $a['avgResolution'],
                    $a['slaPct'] === null ? '—' : $a['slaPct'].'%',
                ])
                ->all(),
        ];
    }

    /** @return array{title:string,columns:array<int,array{label:string,align:string}>,rows:array<int,array<int,string>>} */
    private static function ticketSummary(Collection $tickets): array
    {
        return [
            'title' => 'Ticket Summary Report',
            'columns' => [
                self::col(__('teamlead.report_cols.app')),
                self::col(__('teamlead.report_cols.incident'), 'right'),
                self::col(__('teamlead.report_cols.service'), 'right'),
                self::col(__('teamlead.report_cols.access'), 'right'),
                self::col(__('teamlead.report_cols.total'), 'right'),
            ],
            'rows' => self::counted($tickets)
                ->filter(fn (Ticket $t) => $t->service_name)
                ->groupBy('service_name')
                ->map(fn (Collection $g, string $app) => [
                    $app,
                    (string) $g->where('issue_category', 'Incident')->count(),
                    (string) $g->where('issue_category', 'Service Request')->count(),
                    (string) $g->where('issue_category', 'Access Request')->count(),
                    (string) $g->count(),
                ])
                ->sortByDesc(fn (array $r) => (int) $r[4])
                ->values()->all(),
        ];
    }

    /** @return array{title:string,columns:array<int,array{label:string,align:string}>,rows:array<int,array<int,string>>} */
    private static function topIncident(Collection $tickets): array
    {
        return [
            'title' => 'Top Incident Report',
            'columns' => [
                self::col('#'),
                self::col(__('teamlead.report_cols.issue')),
                self::col(__('teamlead.report_cols.app')),
                self::col(__('teamlead.report_cols.count'), 'right'),
            ],
            'rows' => self::counted($tickets)
                ->groupBy(fn (Ticket $t) => $t->subject_name ?? $t->title)
                ->map(fn (Collection $g, string $name) => [
                    'name' => $name,
                    'apps' => $g->pluck('service_name')->filter()->unique()->take(2)->implode(' · ') ?: '—',
                    'count' => $g->count(),
                ])
                ->sortByDesc('count')
                ->take(self::TOP_INCIDENT_LIMIT)
                ->values()
                ->map(fn (array $r, int $i) => [(string) ($i + 1), $r['name'], $r['apps'], (string) $r['count']])
                ->all(),
        ];
    }

    /** @return array{title:string,columns:array<int,array{label:string,align:string}>,rows:array<int,array<int,string>>} */
    private static function slaCompliance(Collection $tickets): array
    {
        return [
            'title' => 'SLA Compliance Report',
            'columns' => [
                self::col(__('teamlead.report_cols.subcategory')),
                self::col(__('teamlead.report_cols.app')),
                self::col(__('teamlead.report_cols.total'), 'right'),
                self::col(__('teamlead.report_cols.breach'), 'right'),
                self::col(__('teamlead.report_cols.compliance'), 'right'),
            ],
            'rows' => self::counted($tickets)
                ->groupBy(fn (Ticket $t) => $t->subcategory_name ?? $t->issue_category ?? 'Lainnya')
                ->map(function (Collection $g, string $sub) {
                    $total = $g->count();
                    $breach = $g->filter(fn (Ticket $t) => self::isBreached($t))->count();
                    $app = $g->groupBy('service_name')->sortByDesc(fn (Collection $x) => $x->count())->keys()->first() ?? '—';

                    return [
                        'sub' => $sub,
                        'app' => $app,
                        'total' => $total,
                        'breach' => $breach,
                        'comp' => round(($total - $breach) / max($total, 1) * 100, 1),
                    ];
                })
                ->sortByDesc('total')
                ->values()
                ->map(fn (array $r) => [$r['sub'], $r['app'], (string) $r['total'], (string) $r['breach'], $r['comp'].'%'])
                ->all(),
        ];
    }

    /**
     * Tiket yang ikut dihitung: Draft belum pernah masuk antrean, dan Returned
     * sedang dikembalikan ke requester — keduanya bukan beban tim.
     */
    private static function counted(Collection $tickets): Collection
    {
        return $tickets->whereNotIn('status', ['Draft', 'Returned']);
    }

    /**
     * Tiket yang sudah selesai dinilai dari waktu penyelesaiannya; yang masih
     * berjalan dinilai dari sisa SLA-nya saat ini.
     */
    private static function isBreached(Ticket $ticket): bool
    {
        if (in_array($ticket->status, Ticket::DONE_STATUSES, true)) {
            return $ticket->resolved_at !== null
                && $ticket->resolution_due_at !== null
                && $ticket->resolved_at->greaterThan($ticket->resolution_due_at);
        }

        return in_array($ticket->status, Ticket::ACTIVE_STATUSES, true) && $ticket->sla_kind === 'breach';
    }

    /** @return array{label:string,align:string} */
    private static function col(string $label, string $align = 'left'): array
    {
        return ['label' => $label, 'align' => $align];
    }
}
