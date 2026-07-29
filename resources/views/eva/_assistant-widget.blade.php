{{--
 | Widget EVA — asisten mengambang di pojok kanan bawah.
 |
 | Dipasang lewat @include supaya layout mana pun bisa menambahkannya dengan
 | satu baris, dan agar prop-nya hanya ditulis di SATU tempat. Naikkan
 | $evaWidgetOffset pada layout yang pojok kanan bawahnya sudah terpakai tombol
 | lain (mis. "⇄ Switch Role" milik tim di layouts/app, admin, dan requester),
 | supaya keduanya tidak saling menimpa:
 |
 |     @include('eva._assistant-widget', ['evaWidgetOffset' => 96])
 |
 | Widget memanggil endpoint POST, jadi halaman pemasangnya WAJIB punya
 | <meta name="csrf-token"> di <head> — apiFetch membacanya dari sana.
--}}
<div
    data-react="EvaAssistantWidget"
    data-props="{{ json_encode(\App\Support\Eva\AssistantWidget::props($evaWidgetOffset ?? 24)) }}"
></div>
