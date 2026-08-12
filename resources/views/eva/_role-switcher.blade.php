{{--
    Tombol "⇄ Switch Role" untuk konsol EVA.

    Dulu berkas ini menyalin blok $switcherRoles milik tujuh layout tim, dengan
    alasan yang ditulis di sini: repo ini klon repo tim, dan menyentuh berkas
    mereka demi menghemat satu blok berarti tujuh bentrokan pada pull berikutnya.
    Alasan itu sudah lewat — EVA dan helpdesk tim kini hidup di `main` yang sama,
    jadi tidak ada lagi pull yang bisa dibentrokkan, dan delapan salinan aturan
    yang sama justru jadi delapan peluang mereka berbeda pendapat.

    `role` dikunci 'eva' — layout konsol tidak punya variabel $role seperti
    layout tim, dan memang tidak butuh: setiap layar di sini milik peran EVA
    Knowledge.
--}}
@include('partials.role-switcher', ['role' => 'eva'])
