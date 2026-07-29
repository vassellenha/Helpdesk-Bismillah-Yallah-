@extends('layouts.eva')

@section('title', $label)

@section('content')
<div style="padding:48px 32px;max-width:620px">
    <h1 style="font-size:22px;font-weight:700;letter-spacing:-.3px;margin:0 0 10px">{{ $label }}</h1>
    <p style="font-size:14px;line-height:1.65;color:var(--ink-700);margin:0">
        Layar ini belum dibangun. Iterasi pertama mencakup Coverage Dashboard,
        Article Library, Manage FAQ, Documents, dan EVA Preview — menu ini
        tetap ditampilkan supaya bentuk konsolnya jujur, bukan karena ada yang rusak.
    </p>
</div>
@endsection
