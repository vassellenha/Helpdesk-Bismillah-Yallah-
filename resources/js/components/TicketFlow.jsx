/**
 * The ticket's journey as one horizontal stepper — Requester → Approver →
 * Support → Selesai — shared by every role's ticket detail so all five screens
 * read the same. Every string arrives already translated from the server
 * (App\Support\TicketFlow), so this file holds no copy of its own.
 */
const STEP_STYLE = {
    done: { dot: 'bg-emerald-500', line: 'bg-emerald-500', text: 'text-gray-900 dark:text-ink-1' },
    current: { dot: 'bg-blue-500 ring-4 ring-blue-100', line: 'bg-gray-200', text: 'text-blue-700 dark:text-accent-text' },
    pending: { dot: 'bg-gray-300', line: 'bg-gray-200', text: 'text-gray-400 dark:text-ink-3' },
    // A ticket can stop at a stage instead of passing through it.
    rejected: { dot: 'bg-red-500 ring-4 ring-red-100', line: 'bg-gray-200', text: 'text-red-600 dark:text-bad-text' },
    returned: { dot: 'bg-amber-500 ring-4 ring-amber-100', line: 'bg-gray-200', text: 'text-amber-700 dark:text-warn-text' },
};

const NOTE_STYLE = {
    done: 'text-emerald-600 dark:text-ok-text',
    current: 'text-blue-600 dark:text-accent-text',
    rejected: 'text-red-600 dark:text-bad-text',
    returned: 'text-amber-700 dark:text-warn-text',
    pending: 'text-gray-400 dark:text-ink-3',
};

const NOTE_ICON = {
    done: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M8.5 12l2.5 2.5 4.5-5',
    current: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 2',
    rejected: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M15 9l-6 6 M9 9l6 6',
    returned: 'M9 14 4 9l5-5 M4 9h10.5a5.5 5.5 0 0 1 0 11H11',
    pending: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 8v5 M12 16h.01',
};

const CHECK = 'M5 12l4 4 10-11';

export default function TicketFlow({ flow }) {
    if (!flow?.stages?.length) return null;

    return (
        <div>
            <div className="flex items-stretch overflow-x-auto rounded-2xl bg-gray-50 dark:bg-panel-3 p-4">
                {flow.stages.map((s, i) => {
                    const st = STEP_STYLE[s.state] ?? STEP_STYLE.pending;
                    const prev = STEP_STYLE[flow.stages[i - 1]?.state]?.line ?? 'bg-gray-200';

                    return (
                        <div key={s.key ?? i} className="flex min-w-[104px] flex-1 flex-col items-center gap-2 text-center">
                            <div className="flex w-full items-center justify-center">
                                <span className={`h-0.5 flex-1 ${i === 0 ? 'bg-transparent' : prev}`} />
                                <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white ${st.dot}`}>
                                    {s.state === 'done'
                                        ? <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d={CHECK} /></svg>
                                        : <span className="h-1.5 w-1.5 rounded-full bg-white dark:bg-panel-2" />}
                                </span>
                                <span className={`h-0.5 flex-1 ${i === flow.stages.length - 1 ? 'bg-transparent' : st.line}`} />
                            </div>
                            <div className="px-1">
                                <p className={`text-[11.5px] font-bold leading-tight ${st.text}`}>{s.name}</p>
                                {s.sub && <p className="mt-0.5 text-[10px] leading-tight text-gray-400 dark:text-ink-3">{s.sub}</p>}
                                {s.by && <p className="mt-0.5 text-[10px] font-medium leading-tight text-gray-500 dark:text-ink-2">{s.by}</p>}
                                {s.at && <p className="text-[9.5px] leading-tight text-gray-400 dark:text-ink-3">{s.at}</p>}
                            </div>
                        </div>
                    );
                })}
            </div>

            {flow.note && (
                <p className={`mt-2 flex items-start gap-1.5 text-[11.5px] font-semibold ${NOTE_STYLE[flow.noteState] ?? NOTE_STYLE.pending}`}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 shrink-0">
                        <path d={NOTE_ICON[flow.noteState] ?? NOTE_ICON.pending} />
                    </svg>
                    {flow.note}
                </p>
            )}
        </div>
    );
}
