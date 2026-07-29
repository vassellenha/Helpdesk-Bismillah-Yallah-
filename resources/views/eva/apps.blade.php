@extends('layouts.eva')

@section('title', 'Apps & Systems')

@section('content')
<div data-react="EvaAppsSystems" data-props="{{ json_encode([
    'services' => $services,
    'stats' => $stats,
    'catalogUrl' => $catalogUrl,
]) }}"></div>
@endsection
