import { useState } from 'react';
import { apiFetch } from '../../lib/api';

const ICON_SYNC = 'M21 12a9 9 0 1 1-2.64-6.36 M21 3v6h-6';
const ICON_PLUG = 'M9 2v6 M15 2v6 M6 8h12v4a6 6 0 0 1-12 0Z M12 18v4';

function Card({ title, subtitle, actions, children }) {
    return (
        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-6 shadow-sm">
            {(title || actions) && (
                <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        {title && <h2 className="text-sm font-bold text-gray-900 dark:text-ink-1">{title}</h2>}
                        {subtitle && <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{subtitle}</p>}
                    </div>
                    {actions}
                </div>
            )}
            {children}
        </div>
    );
}

function Row({ label, value, hint }) {
    return (
        <div className="flex flex-col gap-0.5 border-b border-gray-50 dark:border-edge py-2.5 last:border-0 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
            <span className="text-[13px] text-gray-500 dark:text-ink-2">{label}</span>
            <span className="text-right text-[13px] font-semibold text-gray-900 dark:text-ink-1">
                {value ?? '—'}
                {hint && <span className="ml-1.5 font-normal text-[11px] text-gray-400 dark:text-ink-3">{hint}</span>}
            </span>
        </div>
    );
}

function Summary({ summary }) {
    const cells = [
        ['Diterima', summary.fetched],
        ['Dibuat', summary.created],
        ['Diperbarui', summary.updated],
        ['Tetap', summary.unchanged],
        ['Dilewati', summary.skipped.length],
        ['Field dipertahankan', summary.kept_empty],
        ['Di luar sumber', (summary.not_in_source ?? []).length],
    ];

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
            {cells.map(([label, n]) => (
                <div key={label} className="rounded-lg bg-gray-50 dark:bg-panel-3 px-3 py-2">
                    <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{label}</p>
                    <p className="mt-0.5 text-lg font-extrabold text-gray-900 dark:text-ink-1">{n}</p>
                </div>
            ))}
        </div>
    );
}

/**
 * Admin → Integrasi. Everything about the employee-directory feed in one place:
 * what is configured, whether it answers, and the log of past runs.
 *
 * The credentials are intentionally absent — they live in .env, and this page
 * only reports whether a token exists. See IntegrationController.
 */
export default function IntegrationConsole({ integration, history: initialHistory = [], syncUrl, testUrl }) {
    const [history, setHistory] = useState(initialHistory);
    const [busy, setBusy] = useState(null); // 'sync' | 'test'
    const [result, setResult] = useState(null); // { ok, message, summary }
    const [error, setError] = useState('');

    async function run(kind) {
        setBusy(kind);
        setError('');
        setResult(null);
        try {
            if (kind === 'test') {
                const res = await apiFetch(testUrl, { method: 'POST' });
                setResult({ ok: true, message: res.message, summary: res.summary, dryRun: true });
            } else {
                const res = await apiFetch(syncUrl, { method: 'POST' });
                setHistory(res.history);
                setResult({
                    ok: true,
                    message: `Sinkronisasi selesai — ${res.summary.fetched} data diterima.`,
                    summary: res.summary,
                });
            }
        } catch (e) {
            setError(e.message || 'Gagal menghubungi sumber data.');
        } finally {
            setBusy(null);
        }
    }

    const lastRun = history[0];

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">Integrasi</h1>
                    <p className="mt-1 text-[13px] text-gray-500 dark:text-ink-2">
                        Sumber data pegawai perusahaan. Kredensial diatur di <code className="rounded bg-gray-100 dark:bg-panel-3 px-1 py-0.5 text-[12px]">.env</code>, bukan di halaman ini.
                    </p>
                </div>
                <div className="flex shrink-0 gap-2">
                    <button
                        onClick={() => run('test')}
                        disabled={busy !== null}
                        className="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={ICON_PLUG} /></svg>
                        {busy === 'test' ? 'Menguji…' : 'Tes Koneksi'}
                    </button>
                    <button
                        onClick={() => run('sync')}
                        disabled={busy !== null}
                        className="flex items-center gap-2 rounded-lg bg-blue-700 dark:bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className={busy === 'sync' ? 'animate-spin' : ''} aria-hidden="true"><path d={ICON_SYNC} /></svg>
                        {busy === 'sync' ? 'Menyinkronkan…' : 'Sync Sekarang'}
                    </button>
                </div>
            </div>

            {error && <p className="rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

            {result && (
                <div className="rounded-2xl bg-emerald-50 dark:bg-ok-soft p-4 text-sm text-emerald-800 dark:text-ok-text">
                    <div className="flex items-start justify-between gap-3">
                        <p>
                            <span className="font-bold">{result.message}</span>
                            {result.dryRun && <span className="ml-1.5 text-xs">(uji coba — tidak ada yang ditulis)</span>}
                        </p>
                        <button onClick={() => setResult(null)} className="shrink-0 rounded p-0.5 hover:bg-emerald-100 dark:hover:bg-panel-hover" aria-label="Tutup">✕</button>
                    </div>
                    <div className="mt-3"><Summary summary={result.summary} /></div>
                    {result.summary.changes.length > 0 && (
                        <ul className="mt-3 list-inside list-disc space-y-0.5 text-xs">
                            {result.summary.changes.map((c) => (
                                <li key={c.name}><span className="font-medium">{c.name}</span> — {c.fields.join(', ')}</li>
                            ))}
                        </ul>
                    )}
                    {result.summary.skipped.length > 0 && (
                        <ul className="mt-3 list-inside list-disc space-y-0.5 text-xs">
                            {result.summary.skipped.map((s) => <li key={s}>{s}</li>)}
                        </ul>
                    )}
                    {(result.summary.not_in_source ?? []).length > 0 && (
                        <p className="mt-3 text-xs opacity-80">
                            Tidak ada di data API, dibiarkan apa adanya: <span className="font-medium">{result.summary.not_in_source.join(', ')}</span>
                        </p>
                    )}
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="flex flex-col gap-6 lg:col-span-2">
                    <Card
                        title="Sumber Data Pegawai"
                        subtitle="Dari sini data user disinkronkan"
                        actions={
                            <span className={`rounded-full px-2.5 py-1 text-[11px] font-bold ${integration.isLive ? 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text' : 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text'}`}>
                                {integration.isLive ? 'LIVE' : 'MOCK'}
                            </span>
                        }
                    >
                        <Row label="Driver" value={integration.driverLabel} hint={`(${integration.driver})`} />
                        {integration.isLive ? (
                            <>
                                <Row label="Base URL" value={integration.baseUrl ?? 'belum diisi'} />
                                <Row label="Endpoint" value={integration.endpoint} />
                                <Row label="Token" value={integration.tokenSet ? 'Terisi' : 'Belum diisi'} hint={integration.tokenSet ? '(nilainya tidak ditampilkan)' : null} />
                                <Row label="Timeout" value={`${integration.timeout} detik`} />
                                <Row label="Kunci koleksi" value={integration.collectionKey || '(body langsung array)'} />
                            </>
                        ) : (
                            <Row label="Fixture" value={<code className="text-[12px]">{integration.fixture}</code>} />
                        )}
                        <Row label="Dicocokkan lewat" value={integration.matchBy} />
                        <Row label="Role user baru" value={integration.defaultRole} />
                        <Row
                            label="Nonaktifkan yang absen"
                            value={integration.deactivateMissing ? 'Ya' : 'Tidak'}
                            hint={integration.deactivateMissing ? null : '(aman — API error tidak menonaktifkan siapa pun)'}
                        />
                        <Row
                            label="Kosong menimpa data"
                            value={integration.overwriteWithEmpty ? 'Ya' : 'Tidak'}
                            hint={integration.overwriteWithEmpty ? '(API pemilik mutlak)' : '(data lokal dipertahankan)'}
                        />

                        {!integration.isLive && (
                            <p className="mt-4 rounded-lg bg-amber-50 dark:bg-warn-soft p-3 text-xs leading-relaxed text-amber-800 dark:text-warn-text">
                                Masih memakai fixture lokal. Untuk menyambung ke API perusahaan, set
                                <code className="mx-1 rounded bg-white/60 dark:bg-panel-2 px-1">EMPLOYEE_DIRECTORY_DRIVER=http</code>
                                di <code className="rounded bg-white/60 dark:bg-panel-2 px-1">.env</code> beserta URL dan token-nya.
                            </p>
                        )}
                    </Card>

                    <Card title="Riwayat Sinkronisasi" subtitle="Dibaca dari Audit Trail — modul “Integrasi”">
                        {history.length === 0 ? (
                            <p className="py-6 text-center text-sm text-gray-400 dark:text-ink-3">Belum pernah disinkronkan.</p>
                        ) : (
                            <div className="flex flex-col">
                                {history.map((h) => (
                                    <div key={h.id} className="flex gap-3 border-b border-gray-50 dark:border-edge py-3 last:border-0">
                                        <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${h.failed ? 'bg-red-500' : 'bg-emerald-500'}`} />
                                        <div className="min-w-0">
                                            <p className={`text-[13px] ${h.failed ? 'text-red-700 dark:text-bad-text' : 'text-gray-800 dark:text-ink-1'}`}>{h.description}</p>
                                            <p className="mt-0.5 text-[11px] text-gray-400 dark:text-ink-3">{h.at} · {h.by}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card title="Sinkronisasi Terakhir">
                        {lastRun ? (
                            <>
                                <p className={`text-[13px] ${lastRun.failed ? 'text-red-700 dark:text-bad-text' : 'text-gray-800 dark:text-ink-1'}`}>{lastRun.description}</p>
                                <p className="mt-1.5 text-[11px] text-gray-400 dark:text-ink-3">{lastRun.at} · {lastRun.by}</p>
                            </>
                        ) : (
                            <p className="text-sm text-gray-400 dark:text-ink-3">Belum ada.</p>
                        )}
                    </Card>

                    <Card title="Pemetaan Field" subtitle="Field API → kolom users">
                        <div className="flex flex-col gap-1.5 text-[12px]">
                            {Object.entries(integration.fieldMap).map(([source, column]) => (
                                <div key={source} className="flex items-center gap-2">
                                    <code className="min-w-0 flex-1 truncate rounded bg-gray-50 dark:bg-panel-3 px-2 py-1 text-gray-700 dark:text-ink-2">{source}</code>
                                    <span className="shrink-0 text-gray-300">→</span>
                                    <code className="min-w-0 flex-1 truncate rounded bg-blue-50 dark:bg-accent-soft px-2 py-1 text-blue-700 dark:text-accent-text">{column}</code>
                                </div>
                            ))}
                        </div>
                        <p className="mt-3 text-[11px] leading-relaxed text-gray-400 dark:text-ink-3">
                            Nama field di kiri masih perkiraan. Saat spesifikasi API asli tersedia, cukup sesuaikan
                            <code className="mx-1">field_map</code> di <code>config/integrations.php</code> — tidak ada kode yang perlu diubah.
                        </p>
                    </Card>

                    <Card title="Pemetaan Status">
                        <div className="flex flex-wrap gap-1.5 text-[11px]">
                            {Object.entries(integration.statusMap).map(([raw, mapped]) => (
                                <span key={raw} className="rounded-full bg-gray-50 dark:bg-panel-3 px-2 py-1 text-gray-600 dark:text-ink-2">
                                    {raw} → <span className={mapped === 'active' ? 'font-semibold text-emerald-600 dark:text-ok-text' : 'font-semibold text-gray-500 dark:text-ink-3'}>{mapped}</span>
                                </span>
                            ))}
                        </div>
                        <p className="mt-3 text-[11px] leading-relaxed text-gray-400 dark:text-ink-3">
                            Kode status di luar daftar ini tidak mengubah status siapa pun — dicatat ke log supaya bisa ditambahkan.
                        </p>
                    </Card>
                </div>
            </div>
        </div>
    );
}
