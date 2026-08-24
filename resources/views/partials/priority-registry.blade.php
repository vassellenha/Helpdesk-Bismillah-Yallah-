{{--
    Daftar prioritas yang berlaku, dititipkan sekali per halaman.

    Sisi React tidak boleh menyimpan daftarnya sendiri. Selama daftar itu
    disalin ke dalam komponen, prioritas buatan Admin tidak pernah sampai ke
    layar — server mengirim lima, komponennya menggambar empat nama yang
    ditulis langsung di dalam kodenya — dan mengganti nama sebuah prioritas
    membuatnya tampak nonaktif serta kehilangan warnanya.

    Ditaruh sebagai JSON, bukan atribut data pada tiap island: lencana
    prioritas muncul di belasan komponen yang tidak semuanya menerima props
    dari Blade.
--}}
<script type="application/json" id="priority-registry">@json(\App\Support\PriorityRegistry::payload())</script>
