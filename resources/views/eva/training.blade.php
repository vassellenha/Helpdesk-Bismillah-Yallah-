@extends('layouts.eva')

@section('title', 'Training Overview')

@section('content')
<div data-react="EvaTrainingOverview" data-props="{{ json_encode([
    'sources' => $sources,
    'readiness' => $readiness,
    'endpoints' => $endpoints,
    'links' => $links,
]) }}"></div>
@endsection
