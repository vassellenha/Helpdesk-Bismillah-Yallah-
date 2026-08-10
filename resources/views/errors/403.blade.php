@extends('layouts.portal')

@section('title', 'Akses Ditolak')

@section('content')
<div class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center px-6 py-16 text-center">
    <p class="text-sm font-semibold tracking-wide text-blue-700 dark:text-accent-text">
        {{ strtoupper(config('helpdesk.company')) }} · {{ strtoupper(config('helpdesk.product')) }}
    </p>

    <span class="mt-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
        </svg>
    </span>

    <h1 class="mt-6 text-2xl font-extrabold text-gray-900 dark:text-ink-1">Anda Tidak Punya Akses ke Halaman Ini</h1>
    <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-ink-3">
        {{ $exception->getMessage() ?: 'Item ini bukan tanggung jawab akun/role yang sedang aktif. Jika kamu baru saja berpindah "Bertindak sebagai" di tab lain, coba muat ulang halaman ini.' }}
    </p>

    {{--
        Akun nonaktif TIDAK diberi tombol "Pilih Role".

        Keduanya bermuara ke portal pemilih role, dan role mana pun yang diklik
        di sana ditolak lagi oleh gerbang yang sama. Tombol yang memutar kembali
        ke halaman ini bukan cuma tak berguna — ia membuat orang mengira
        masalahnya salah pilih role, lalu mencoba ketujuhnya satu per satu.

        Tapi untuk TIGA role yang punya mekanisme "bertindak sebagai" (support,
        support-bpo, approver — lihat CurrentActor::support()/supportBpo()/
        approver()), persona yang macet itu cuma satu dari beberapa yang sah.
        Kalau ada agent/approver aktif lain, tawarkan pindah ke situ langsung
        dari sini — gerbangnya sama, tapi orangnya beda, jadi tidak ditolak
        lagi. "Hubungi Administrator" tetap tampil sebagai jalan yang benar-
        benar menyelesaikan (memperbaiki akun aslinya), bukan cuma workaround.
    --}}
    @php
        // getPrevious(), bukan $exception itu sendiri: Laravel membungkus setiap
        // AuthorizationException jadi HttpException sebelum merender halaman ini,
        // dan menyimpan yang asli sebagai "previous". Memeriksa $exception
        // langsung selalu meleset, tanpa error — cabang else yang jalan.
        $akunNonaktif = $exception->getPrevious() instanceof \App\Exceptions\AccountInactive;

        $switchContext = null;
        if ($akunNonaktif) {
            if (request()->routeIs('dashboard.support-bpo') || request()->routeIs('support-bpo.*')) {
                $switchContext = ['field' => 'agent_id', 'switchUrl' => route('support-bpo.switch-agent'), 'options' => \App\Support\ActingSwitcher::agentOptions('bpo')];
            } elseif (request()->routeIs('dashboard.support') || request()->routeIs('support.*')) {
                $switchContext = ['field' => 'agent_id', 'switchUrl' => route('support.switch-agent'), 'options' => \App\Support\ActingSwitcher::agentOptions('it')];
            } elseif (request()->routeIs('dashboard.approver') || request()->routeIs('approver.*')) {
                $switchContext = ['field' => 'approver_id', 'switchUrl' => route('approver.switch-approver'), 'options' => \App\Support\ActingSwitcher::approverOptions()];
            }
        }
    @endphp

    @if ($akunNonaktif)
        @if ($switchContext && $switchContext['options']->isNotEmpty())
            <form method="POST" action="{{ $switchContext['switchUrl'] }}" class="mt-8 flex items-center gap-3">
                @csrf
                <select
                    name="{{ $switchContext['field'] }}"
                    class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-3 py-2.5 text-sm font-semibold text-gray-700 dark:text-ink-2 outline-none focus:border-blue-400"
                >
                    @foreach ($switchContext['options'] as $opt)
                        <option value="{{ $opt->id }}">{{ $opt->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-xl bg-blue-700 dark:bg-blue-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 dark:hover:bg-blue-400">
                    Bertindak sebagai
                </button>
            </form>
        @endif
        <p class="mt-6 text-sm text-gray-400 dark:text-ink-3">
            Hubungi Administrator Helpdesk untuk mengaktifkan kembali akun Anda.
        </p>
    @else
        <div class="mt-8 flex items-center gap-3">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('portal.index') }}" class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-5 py-2.5 text-sm font-semibold text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover">
                ← Kembali
            </a>
            <a href="{{ route('portal.index') }}" class="rounded-xl bg-blue-700 dark:bg-blue-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 dark:hover:bg-blue-400">
                Pilih Role
            </a>
        </div>
    @endif
</div>
@endsection
