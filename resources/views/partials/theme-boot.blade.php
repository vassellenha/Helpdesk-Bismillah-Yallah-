{{--
  Memasang kelas `.dark` pada <html> sebelum apa pun dilukis.

  WAJIB di-include oleh SETIAP layout, dan diletakkan paling atas di <head>.
  Sempat hanya dipasang di layouts/app.blade.php, dan gejalanya persis seperti
  fitur yang rusak: tema bertahan selama menjelajah halaman-halaman yang memakai
  layout itu, lalu tiba-tiba kembali terang begitu berpindah ke halaman dengan
  layout lain — Dashboard dan My Tickets memang memakai layout berbeda.

  Temanya disimpan di COOKIE, bukan localStorage. Dua alasan:

  1. Cookie ikut terkirim ke server di setiap permintaan, jadi kalau nanti
     kelasnya mau dirender langsung dari Blade (`<html class="dark">`),
     datanya sudah ada di sana tanpa perubahan apa pun di sisi klien.
  2. localStorage terpisah per origin dan tidak pernah sampai ke server; ia
     bekerja, tapi menutup pintu ke nomor 1.

  Bukan session Laravel: mengubah session menuntut satu permintaan ke server
  setiap kali tombolnya ditekan, padahal yang berubah cuma warna. Cookie ditulis
  langsung di browser dan efeknya seketika.

  Skrip inline, bukan berkas terpisah — satu permintaan berkas tambahan di sini
  justru mengembalikan kedipan yang mau dihindari.
--}}
<script>
    (function () {
        try {
            var cocok = document.cookie.match(/(?:^|;\s*)helpdesk_theme=(dark|light)/);
            // Belum pernah memilih → ikuti OS. Sudah memilih → pilihannya menang,
            // termasuk saat OS berganti ke jadwal malam.
            var gelap = cocok
                ? cocok[1] === 'dark'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;

            document.documentElement.classList.toggle('dark', gelap);
        } catch (e) {
            // Cookie bisa dilarang di sebagian mode privat. Diamkan: halaman
            // tetap tampil, hanya jatuh ke mode terang.
        }
    })();
</script>
