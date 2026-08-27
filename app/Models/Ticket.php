<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $ticket_no
 * @property string $title
 * @property string|null $requester_name
 * @property int|null $requester_id
 * @property string|null $service_name
 * @property string|null $subcategory_name
 * @property string|null $subject_name
 * @property string|null $issue_category
 * @property string|null $description
 * @property string|null $category
 * @property int|null $sla_policy_id
 * @property string $priority
 * @property int|null $approver_id
 * @property int|null $assigned_agent_id
 * @property int|null $catalog_subject_id
 * @property int $response_time_minutes
 * @property int $resolution_time_minutes
 * @property int $warning_threshold_percent
 * @property Carbon $response_due_at
 * @property Carbon $resolution_due_at
 * @property Carbon $warning_at
 * @property string $status
 * @property bool $is_draft
 * @property Carbon|null $resolved_at
 * @property int|null $satisfaction_rating
 * @property bool $rating_active
 * @property string|null $feedback_note
 * @property Carbon|null $escalated_at
 * @property string|null $escalation_note
 * @property int|null $escalated_by_agent_id
 * @property string|null $reopen_note
 * @property Carbon|null $reopen_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $sla_status
 * @property-read int|null $sla_minutes_remaining
 * @property-read int $sla_elapsed_percent
 * @property-read int $effective_resolution_minutes
 * @property-read int|null $response_minutes_remaining
 * @property-read string $response_kind
 * @property-read string $response_label
 * @property-read int $response_elapsed_percent
 * @property-read \Illuminate\Support\Carbon|null $sla_ended_at
 * @property-read string $response_target_label
 * @property-read string $resolution_target_label
 * @property-read string $sla_kind
 * @property-read string $sla_label
 * @property-read SlaPolicy|null $slaPolicy
 * @property-read User|null $requester
 * @property-read User|null $approver
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketComment> $comments
 * @property-read SupportAgent|null $assignedAgent
 * @property-read ServiceCatalogSubject|null $catalogSubject
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketNotification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketApproval> $approvals
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketAttachment> $attachments
 */
class Ticket extends Model
{
    public const ACTIVE_STATUSES = ['Waiting for Approval', 'Open', 'Assigned', 'In Progress', 'Waiting for Response'];

    public const DONE_STATUSES = ['Resolved', 'Completed', 'Closed'];

    /** Statuses with no active SLA countdown — never submitted, not yet decided, or dead-ended. */
    public const NO_SLA_STATUSES = ['Draft', 'Returned', 'Waiting for Approval', 'Rejected'];

    /**
     * Statuses where the ticket hasn't actually reached Support yet — still
     * being drafted/edited by the Requester, sitting with an Approver, or
     * dead-ended before ever routing to Support. `assigned_agent_id` is
     * frozen at creation time (see TicketController::store()), so without
     * this check Support could otherwise see/act on a ticket the Approver
     * hasn't released to them.
     */
    public const NOT_YET_RELEASED_STATUSES = ['Draft', 'Returned', 'Waiting for Approval', 'Rejected'];

    protected $fillable = [
        'ticket_no', 'title', 'requester_name', 'requester_id',
        'service_name', 'subcategory_name', 'subject_name', 'issue_category', 'description',
        'category', 'sla_policy_id', 'priority', 'approver_id', 'assigned_agent_id', 'catalog_subject_id',
        'service_catalog_service_id',
        'response_time_minutes', 'resolution_time_minutes', 'warning_threshold_percent',
        'response_due_at', 'resolution_due_at', 'warning_at',
        'sla_started_at', 'first_response_at', 'sla_extension_minutes',
        'it_response_due_at', 'it_first_response_at',
        'status', 'is_draft', 'resolved_at', 'satisfaction_rating', 'rating_active', 'feedback_note',
        'escalated_at', 'escalation_note', 'escalated_by_agent_id',
        'reopen_note', 'reopen_at',
    ];

    protected $casts = [
        'response_due_at' => 'datetime',
        'it_response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'warning_at' => 'datetime',
        'reopen_at' => 'datetime',
        'resolved_at' => 'datetime',
        'escalated_at' => 'datetime',
        'sla_started_at' => 'datetime',
        'first_response_at' => 'datetime',
        'it_first_response_at' => 'datetime',
        'sla_extension_minutes' => 'integer',
        'is_draft' => 'boolean',
        'rating_active' => 'boolean',
    ];

    public function slaPolicy()
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at');
    }

    public function assignedAgent()
    {
        return $this->belongsTo(SupportAgent::class, 'assigned_agent_id');
    }

    // The BPO agent who escalated this ticket to IT — kept separately from
    // assignedAgent() because escalate() overwrites assigned_agent_id with
    // the IT agent, and this is how that BPO agent keeps read/comment access
    // to a ticket that's no longer theirs to act on. See SupportBpoController.
    public function escalatedByAgent()
    {
        return $this->belongsTo(SupportAgent::class, 'escalated_by_agent_id');
    }

    public function catalogService()
    {
        return $this->belongsTo(ServiceCatalogService::class, 'service_catalog_service_id');
    }

    public function catalogSubject()
    {
        return $this->belongsTo(ServiceCatalogSubject::class, 'catalog_subject_id');
    }

    public function notifications()
    {
        return $this->hasMany(TicketNotification::class);
    }

    public function approvals()
    {
        return $this->hasMany(TicketApproval::class)->orderBy('created_at');
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class)->orderBy('created_at');
    }

    /**
     * Shared shape for `attachments` across every ticket-detail/list
     * endpoint (Requester, Approver, Support, Support BPO, Admin), so a
     * ticket's files are presented identically everywhere instead of each
     * controller hand-rolling the Storage URL lookup.
     */
    /**
     * A row can outlive its file — storage wiped, restored from a DB-only
     * backup, cleaned up by hand. Report that explicitly instead of handing the
     * UI a URL that 404s, which renders as a broken-image icon with no
     * explanation of what went wrong.
     */
    public function attachmentsPayload(): array
    {
        return $this->attachments->map(function (TicketAttachment $a) {
            $exists = $a->path && Storage::disk('local')->exists($a->path);

            return [
                'id' => $a->id,
                'name' => $a->name,
                // Rute ber-otorisasi, bukan URL /storage langsung: berkasnya
                // ada di disk privat dan hanya keluar lewat gerbang yang
                // memeriksa siapa yang meminta.
                'url' => $exists ? route('tickets.attachment.show', [$this, $a]) : null,
                'missing' => ! $exists,
            ];
        })->values()->all();
    }

    /**
     * Ticket numbers are unique and far more readable in a URL than the
     * numeric id, so `{ticket}` route params resolve by ticket_no.
     */
    public function getRouteKeyName(): string
    {
        return 'ticket_no';
    }

    /**
     * SLA status computed live against server time, never stored — a ticket's
     * snapshot (due dates) is fixed at creation, but its Within/Warning/Breach
     * state must always reflect "now".
     */
    public function getSlaStatusAttribute(): string
    {
        $now = Carbon::now();

        if ($now->greaterThanOrEqualTo($this->resolution_due_at)) {
            return 'Breach';
        }

        if ($now->greaterThanOrEqualTo($this->warning_at)) {
            return 'Warning';
        }

        return 'Within SLA';
    }

    /**
     * Signed minutes to the resolution deadline (negative once breached).
     * Drives both the "Tickets Approaching SLA Limit" sort order and the
     * table's countdown label — kept as one number so sorting never drifts
     * out of sync with what the label says.
     */
    public function getSlaMinutesRemainingAttribute(): ?int
    {
        if (in_array($this->status, self::NO_SLA_STATUSES, true)) {
            return null;
        }

        if (in_array($this->status, self::DONE_STATUSES, true)) {
            return $this->resolved_at
                ? (int) $this->resolved_at->diffInMinutes($this->resolution_due_at, false)
                : 0;
        }

        return (int) Carbon::now()->diffInMinutes($this->resolution_due_at, false);
    }

    public function getResponseTargetLabelAttribute(): string
    {
        return self::humanMinutes($this->response_time_minutes);
    }

    public function getResolutionTargetLabelAttribute(): string
    {
        return self::humanMinutes($this->resolution_time_minutes);
    }

    /**
     * The resolution window actually in force: the policy's commitment plus
     * whatever escalation has granted. Kept separate from
     * resolution_time_minutes so the original promise stays readable.
     */
    public function getEffectiveResolutionMinutesAttribute(): int
    {
        return $this->resolution_time_minutes + $this->sla_extension_minutes;
    }

    /*
    |--------------------------------------------------------------------------
    | Response SLA
    |--------------------------------------------------------------------------
    |
    | Deliberately its own clock, not a slice of the resolution one. The two
    | answer different questions — "did anyone pick this up in time?" versus
    | "was it finished in time?" — and they stop at different moments: response
    | freezes the instant Support first replies (first_response_at), while
    | resolution keeps running until the ticket is resolved. A ticket can meet
    | one and breach the other, so neither may be derived from the other.
    |
    */

    /**
     * Jam respons TAHAP YANG SEDANG BERJALAN.
     *
     * Tiket berpindah tangan sekali: BPO dulu, IT setelah dieskalasi. Keduanya
     * punya pertanyaan yang sama ("ada yang menanggapi tepat waktu?") tapi
     * jawabannya beda orang dan beda jendela waktu, jadi jamnya juga dua.
     * \`escalated_at\` yang memilih — kosong berarti masih jam BPO, terisi
     * berarti jam IT (lihat migrasi add_it_response_clock_to_tickets_table).
     *
     * Kolom BPO tidak pernah ditimpa: \`first_response_at\` tetap berarti
     * "respons pertama atas tiket ini", yang dibaca TeamLeadController untuk
     * rata-rata waktu respons tim.
     */
    public function getActiveResponseDueAtAttribute(): ?Carbon
    {
        return $this->escalated_at !== null ? $this->it_response_due_at : $this->response_due_at;
    }

    /** Pasangan dari activeResponseDueAt — respons tahap yang sedang berjalan. */
    public function getActiveFirstResponseAtAttribute(): ?Carbon
    {
        return $this->escalated_at !== null ? $this->it_first_response_at : $this->first_response_at;
    }

    /** Signed minutes to the response deadline; frozen once Support has replied. */
    public function getResponseMinutesRemainingAttribute(): ?int
    {
        $dueAt = $this->active_response_due_at;

        if (in_array($this->status, self::NO_SLA_STATUSES, true) || $dueAt === null) {
            return null;
        }

        $respondedAt = $this->active_first_response_at;

        if ($respondedAt !== null) {
            return (int) $respondedAt->diffInMinutes($dueAt, false);
        }

        // A ticket that finished without a recorded first response has nothing
        // left to wait for, so the overrun freezes where the work stopped. Left
        // on now() it keeps growing, and a ticket closed months ago still reads
        // as running further behind every day someone opens it.
        $stoppedAt = $this->sla_ended_at ?? Carbon::now();

        // $dueAt, bukan $this->response_due_at: tenggat yang dipakai adalah
        // milik tahap yang sedang berjalan — BPO sebelum eskalasi, IT sesudahnya.
        return (int) $stoppedAt->diffInMinutes($dueAt, false);
    }

    /** met | breach | warning | ontrack | none */
    public function getResponseKindAttribute(): string
    {
        $minutes = $this->response_minutes_remaining;

        if ($minutes === null) {
            return 'none';
        }

        if ($this->active_first_response_at !== null) {
            return $minutes >= 0 ? 'met' : 'breach';
        }

        if ($minutes <= 0) {
            return 'breach';
        }

        // Same warning threshold the resolution clock uses, applied to this window.
        $consumed = $this->response_time_minutes > 0
            ? (1 - $minutes / $this->response_time_minutes) * 100
            : 0;

        return $consumed >= $this->warning_threshold_percent ? 'warning' : 'ontrack';
    }

    public function getResponseLabelAttribute(): string
    {
        $minutes = $this->response_minutes_remaining;

        if ($minutes === null) {
            return '—';
        }

        if ($this->active_first_response_at !== null) {
            return $minutes >= 0
                ? 'Direspons '.self::formatDuration($minutes).' sebelum batas'
                : 'Terlambat respons '.self::formatDuration(abs($minutes));
        }

        return $minutes <= 0
            ? 'Belum direspons · lewat '.self::formatDuration(abs($minutes))
            : 'Belum direspons · sisa '.self::formatDuration($minutes);
    }

    public function getResponseElapsedPercentAttribute(): int
    {
        if ($this->response_minutes_remaining === null || $this->response_time_minutes <= 0) {
            return 0;
        }

        if ($this->response_kind === 'breach') {
            return 100;
        }

        $elapsed = (int) round((1 - max($this->response_minutes_remaining, 0) / $this->response_time_minutes) * 100);

        return max(0, min(100, $elapsed));
    }

    /** When the resolution clock stopped, or null while it is still running. */
    public function getSlaEndedAtAttribute(): ?Carbon
    {
        return in_array($this->status, self::DONE_STATUSES, true) ? $this->resolved_at : null;
    }

    /**
     * Kapan tiket ini akan menutup sendiri kalau requester tidak
     * mengonfirmasi — atau null kalau hitungannya memang tidak berjalan.
     *
     * Hanya hidup pada status Resolved. Itu bukan sekadar penyaring: reopen
     * mengosongkan resolved_at, jadi begitu tiket dibuka kembali hitungannya
     * ikut hilang dengan sendirinya, bukan meneruskan sisa hitungan lama.
     *
     * Tinggal di model, bukan di penyapunya, karena dua pihak membaca angka
     * yang sama — perintah terjadwal memakainya untuk memutuskan, dan layar
     * requester memakainya untuk menampilkan countdown. Kalau keduanya
     * menghitung sendiri-sendiri, yang terlihat di layar dan yang terjadi di
     * basis data bisa berbeda sehari tanpa ada yang sadar.
     */
    public function getAutoCloseAtAttribute(): ?Carbon
    {
        $days = self::autoCloseAfterDays();

        if ($days <= 0 || $this->status !== 'Resolved' || ! $this->resolved_at) {
            return null;
        }

        return $this->resolved_at->clone()->addDays($days);
    }

    /** Tenggang aktif dalam hari; 0 atau kurang berarti fitur dimatikan. */
    public static function autoCloseAfterDays(): int
    {
        return (int) config('helpdesk.auto_close_resolved_after_days', 3);
    }

    /**
     * Bahan countdown untuk layar requester. Mengirim tenggat dalam ISO-8601
     * supaya sisa waktunya dihitung ulang di browser tiap detik — angka yang
     * dirender di server akan langsung basi begitu halaman dibiarkan terbuka.
     *
     * `minutesRemaining` tetap ikut agar sisi server punya satu sumber angka
     * yang sama untuk diuji, dan boleh negatif: tiket yang tenggatnya sudah
     * lewat tapi belum tersapu (penyapu berjalan tiap jam) harus terbaca
     * "sedang ditutup", bukan melompat ke angka positif.
     */
    public function autoClosePayload(): ?array
    {
        $at = $this->auto_close_at;

        if (! $at) {
            return null;
        }

        return [
            'at' => $at->toIso8601String(),
            'atLabel' => $at->translatedFormat('j M Y · H:i'),
            'minutesRemaining' => (int) round(Carbon::now()->diffInMinutes($at, false)),
            'days' => self::autoCloseAfterDays(),
        ];
    }

    /** "90 minutes" / "4 hours" / "2 days" — one wording for every role's SLA panel. */
    private static function humanMinutes(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            return ($minutes / 1440).' day'.($minutes / 1440 > 1 ? 's' : '');
        }

        if ($minutes % 60 === 0) {
            return ($minutes / 60).' hour'.($minutes / 60 > 1 ? 's' : '');
        }

        return "{$minutes} minutes";
    }

    /**
     * How much of the resolution window has been consumed, 0–100, for the SLA
     * progress bar. Lives here rather than in a controller because every role's
     * ticket detail draws the same bar and they must not drift apart.
     */
    public function getSlaElapsedPercentAttribute(): int
    {
        $window = $this->effective_resolution_minutes;

        if ($this->sla_minutes_remaining === null || $window <= 0) {
            return 0;
        }

        if ($this->sla_kind === 'breach') {
            return 100;
        }

        // Measured against the extended window, otherwise granting extra time on
        // escalation would make the bar jump backwards.
        $elapsed = (int) round((1 - max($this->sla_minutes_remaining, 0) / $window) * 100);

        return max(0, min(100, $elapsed));
    }

    /**
     * Grants extra resolution time because the ticket changed hands. Pushes the
     * resolution deadline and the warning mark out together — moving only the
     * deadline would leave the warning already in the past, so the ticket would
     * light up amber the instant it was escalated.
     *
     * The bonus comes from the ticket's own SLA policy ("Escalated Time" in
     * Admin > Konfigurasi SLA), not a single app-wide percentage — a Critical
     * policy and a Low policy have no reason to grant the same bonus.
     *
     * Returns the minutes granted (0 when the ticket has no live clock, e.g. it
     * is already resolved, so an escalation never revives a finished SLA).
     */
    public function grantEscalationExtension(): int
    {
        if (in_array($this->status, [...self::DONE_STATUSES, ...self::NO_SLA_STATUSES], true)) {
            return 0;
        }

        $minutes = (int) ($this->slaPolicy?->escalation_extension_minutes ?? 0);

        if ($minutes <= 0) {
            return 0;
        }

        $this->forceFill([
            'sla_extension_minutes' => $this->sla_extension_minutes + $minutes,
            'resolution_due_at' => $this->resolution_due_at?->clone()->addMinutes($minutes),
            'warning_at' => $this->warning_at?->clone()->addMinutes($minutes),
        ])->save();

        return $minutes;
    }

    /**
     * Stamps the moment Support first replied, which stops the response clock
     * OF THE STAGE THE TICKET IS IN. Idempotent per stage — only the first
     * reply counts, later ones must not reset it.
     *
     * Setelah eskalasi, stempelnya jatuh ke kolom IT. Tanpa pemisahan itu
     * metode ini akan langsung mengembalikan false selamanya (kolom BPO-nya
     * sudah terisi sejak BPO menekan Eskalasi), dan tahap IT tidak akan pernah
     * tercatat kapan ditanggapi.
     */
    public function markFirstResponse(?Carbon $at = null): bool
    {
        $kolom = $this->escalated_at !== null ? 'it_first_response_at' : 'first_response_at';

        if ($this->{$kolom} !== null) {
            return false;
        }

        $this->forceFill([$kolom => $at ?? Carbon::now()])->save();

        return true;
    }

    /**
     * Memulai jam respons tahap IT, dihitung dari sekarang memakai
     * \`response_time_minutes\` tiket ini — angka yang sama dengan yang dipakai
     * tahap BPO, jadi policy Critical tetap lebih ketat daripada Low tanpa
     * perlu kolom konfigurasi baru.
     *
     * Dipanggil SETELAH \`escalated_at\` terisi, dan hanya sekali: eskalasi
     * kedua (kalau suatu saat ada) tidak boleh memberi IT jendela baru dengan
     * menghapus keterlambatan yang sudah terjadi.
     */
    public function startItResponseClock(?Carbon $from = null): void
    {
        if ($this->it_response_due_at !== null || $this->response_time_minutes <= 0) {
            return;
        }

        $this->forceFill([
            'it_response_due_at' => ($from ?? Carbon::now())->clone()->addMinutes($this->response_time_minutes),
        ])->save();
    }

    /**
     * Everything a ticket's SLA panel needs, in one shape every role shares —
     * five screens drawing the same numbers from five hand-rolled arrays is how
     * they drift apart.
     *
     * @return array<string,mixed>
     */
    public function slaPayload(): array
    {
        return [
            'label' => $this->sla_label,
            'kind' => $this->sla_kind,
            'pct' => $this->sla_elapsed_percent,
            'responseTarget' => $this->response_target_label,
            'resolutionTarget' => $this->resolution_target_label,
            'priority' => $this->priority,
            'startedAt' => optional($this->sla_started_at ?? $this->created_at)->format('d M Y · H:i'),
            'endedAt' => optional($this->sla_ended_at)->format('d M Y · H:i'),
            'dueAt' => optional($this->resolution_due_at)->format('d M Y · H:i'),
            'response' => [
                'label' => $this->response_label,
                'kind' => $this->response_kind,
                'pct' => $this->response_elapsed_percent,
                'at' => optional($this->active_first_response_at)->format('d M Y · H:i'),
                'dueAt' => optional($this->active_response_due_at)->format('d M Y · H:i'),
                // Tahap mana yang sedang diukur — dipakai UI untuk memberi label
                // "Respons BPO" atau "Respons IT" alih-alih "Respons" polos,
                // supaya angkanya tidak terbaca sebagai satu jam yang sama.
                'stage' => $this->escalated_at !== null ? 'it' : 'bpo',
            ],
            // Rekam jejak kedua tahap, ditampilkan berdampingan begitu tiket
            // dieskalasi. Tahap BPO tidak hilang hanya karena giliran sudah
            // berpindah — justru di situ terlihat siapa yang lambat.
            'responseStages' => $this->escalated_at === null ? null : [
                [
                    'label' => 'BPO',
                    'at' => optional($this->first_response_at)->format('d M Y · H:i'),
                    'dueAt' => optional($this->response_due_at)->format('d M Y · H:i'),
                    'late' => $this->first_response_at !== null && $this->response_due_at !== null
                        && $this->first_response_at->greaterThan($this->response_due_at),
                ],
                [
                    'label' => 'IT',
                    'at' => optional($this->it_first_response_at)->format('d M Y · H:i'),
                    'dueAt' => optional($this->it_response_due_at)->format('d M Y · H:i'),
                    'late' => $this->it_first_response_at !== null && $this->it_response_due_at !== null
                        && $this->it_first_response_at->greaterThan($this->it_response_due_at),
                ],
            ],
            'extensionMinutes' => $this->sla_extension_minutes,
            'extensionLabel' => $this->sla_extension_minutes > 0
                ? self::humanMinutes($this->sla_extension_minutes)
                : null,
        ];
    }

    public function getSlaKindAttribute(): string
    {
        if (in_array($this->status, self::DONE_STATUSES, true)) {
            $minutes = $this->sla_minutes_remaining;

            return $minutes !== null && $minutes <= 0 ? 'breach' : 'met';
        }

        $minutes = $this->sla_minutes_remaining;

        if ($minutes === null) {
            return 'none';
        }

        if ($minutes <= 0) {
            return 'breach';
        }

        $warningMinutes = Carbon::now()->diffInMinutes($this->warning_at, false);

        return $warningMinutes <= 0 ? 'warning' : 'ontrack';
    }

    public function getSlaLabelAttribute(): string
    {
        if (in_array($this->status, self::DONE_STATUSES, true)) {
            $minutes = $this->sla_minutes_remaining;

            return $minutes !== null && $minutes <= 0
                ? 'Breach +'.self::formatDuration(abs($minutes))
                : 'Selesai dalam SLA';
        }

        if ($this->status === 'Waiting for Approval') {
            return 'Not started';
        }

        if (in_array($this->status, ['Draft', 'Returned', 'Rejected'], true)) {
            return '—';
        }

        $minutes = $this->sla_minutes_remaining ?? 0;

        if ($minutes <= 0) {
            return 'Breach +'.self::formatDuration(abs($minutes));
        }

        return self::formatDuration($minutes).' left';
    }

    protected static function formatDuration(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        if ($days > 0) {
            return $hours > 0 ? "{$days}d {$hours}h" : "{$days}d";
        }

        if ($hours > 0) {
            return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
        }

        return "{$mins} min";
    }
}
