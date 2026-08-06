{{--
    Menu Admin Console, dipakai dua kali dengan gaya berbeda: satu baris inline
    di sebelah logo untuk layar lebar, satu baris tersendiri di bawah header
    untuk layar sempit.

    Dijadikan partial supaya daftar menunya tidak diketik dua kali — kalau
    config('helpdesk.admin_nav') bertambah, dua-duanya ikut bertambah. Sebelum
    ini navnya hanya ada satu dan ditulis `hidden lg:flex`, jadi di bawah 1024px
    Admin sama sekali tidak punya jalan pindah halaman.

    Pemakaian: @include('partials.admin-nav', ['navClass' => '...'])
--}}
<nav class="{{ $navClass }}">
    @foreach (config('helpdesk.admin_nav') as $item)
        <a
            href="{{ route($item['route']) }}"
            class="shrink-0 whitespace-nowrap rounded-lg px-2.5 py-2 text-sm font-medium transition-all duration-200 ease-out {{ request()->routeIs($item['route']) ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' : 'text-gray-600 dark:text-ink-2 hover:bg-white/60 dark:hover:bg-white/10' }}"
        >
            {{ __('admin.nav.'.$item['key']) }}
        </a>
    @endforeach
</nav>
