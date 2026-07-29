@extends('layouts.eva')

@section('title', 'EVA Preview')

@section('content')
<div data-react="EvaPreview" data-props="{{ json_encode([
    'endpoints' => $endpoints,
    'thresholds' => $thresholds,
]) }}"></div>
@endsection
