import { SlaRatingRow } from './RatingStars';

const SLA_COLOR = { ontrack: '#10b981', warning: '#d97706', breach: '#dc2626', met: '#10b981', 'met-late': '#d97706', none: '#9ca3af' };

const RESPONSE_TONE = {
    met: 'text-emerald-600 dark:text-ok-text',
    ontrack: 'text-emerald-600 dark:text-ok-text',
    warning: 'text-amber-600 dark:text-warn-text',
    breach: 'text-red-600 dark:text-bad-text',
    none: 'text-gray-400 dark:text-ink-3',
};

function Row({ label, value, tone }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="shrink-0 text-gray-500 dark:text-ink-2">{label}</span>
            <span className={`text-right font-semibold ${tone ?? 'text-gray-800 dark:text-ink-1'}`}>{value ?? '—'}</span>
        </div>
    );
}

/**
 * The SLA panel body, identical for every role — Requester, Approver, Support
 * IT/BPO, Team Lead and Admin all render this from Ticket::slaPayload(), so the
 * same ticket can never show a different deadline depending on who is looking.
 *
 * Response and resolution are shown as two separate clocks on purpose: they
 * answer different questions and stop at different moments, so a ticket can
 * meet one and breach the other.
 */
export default function SlaPanel({ sla, rating, feedbackNote, ratingActive = true }) {
    if (!sla) {
        return null;
    }

    const response = sla.response ?? {};

    return (
        <div>
            <div className="mb-3 flex items-center justify-between gap-3">
                <span className="text-[13px] text-gray-500 dark:text-ink-2">Penyelesaian</span>
                <span className="text-right text-[13px] font-bold" style={{ color: SLA_COLOR[sla.kind] ?? SLA_COLOR.none }}>
                    {sla.label}
                </span>
            </div>
            <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                <div
                    className="h-full rounded-full"
                    style={{ width: `${sla.pct ?? 0}%`, backgroundColor: SLA_COLOR[sla.kind] ?? SLA_COLOR.none }}
                />
            </div>

            <div className="mt-4 space-y-2 border-t border-gray-100 dark:border-edge pt-3.5 text-[13px]">
                <Row label="Mulai" value={sla.startedAt} />
                <Row label="Selesai" value={sla.endedAt ?? `Target ${sla.dueAt ?? '—'}`} />
                <Row label="Target Respons" value={sla.responseTarget} />
                <Row label="Target Penyelesaian" value={sla.resolutionTarget} />
                {sla.priority && <Row label="Prioritas" value={sla.priority} />}
            </div>

            <div className="mt-3.5 border-t border-gray-100 dark:border-edge pt-3.5 text-[13px]">
                <Row label="Kecepatan Respons" value={response.label} tone={RESPONSE_TONE[response.kind] ?? RESPONSE_TONE.none} />
                {response.at && <Row label="Direspons pada" value={response.at} />}
                {!response.at && response.dueAt && <Row label="Batas respons" value={response.dueAt} />}
                <div className="mt-2 h-1 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                    <div
                        className="h-full rounded-full"
                        style={{
                            width: `${response.pct ?? 0}%`,
                            backgroundColor: response.kind === 'breach' ? SLA_COLOR.breach : response.kind === 'warning' ? SLA_COLOR.warning : SLA_COLOR.ontrack,
                        }}
                    />
                </div>
            </div>

            {sla.extensionLabel && (
                <p className="mt-3.5 rounded-lg bg-amber-50 dark:bg-warn-soft p-2.5 text-[12px] leading-relaxed text-amber-800 dark:text-warn-text">
                    Batas resolusi diperpanjang <span className="font-bold">{sla.extensionLabel}</span> karena tiket dieskalasi.
                </p>
            )}

            <div className="mt-3.5">
                <SlaRatingRow rating={rating} note={feedbackNote} ratingActive={ratingActive} />
            </div>
        </div>
    );
}
