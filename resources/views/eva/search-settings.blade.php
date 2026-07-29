@extends('layouts.eva')

@section('title', 'Search Settings')

@section('content')
<div data-react="EvaSearchSettings" data-props="{{ json_encode([
    'synonyms' => $synonyms,
    'threshold' => $threshold,
    'endpoints' => $endpoints,
]) }}"></div>
@endsection
