@extends('layouts.eva')

@section('title', 'Coverage Dashboard')

@section('content')
<div data-react="EvaCoverageDashboard" data-props="{{ json_encode([
    'summary' => $summary,
    'bySubcategory' => $bySubcategory,
    'trend' => $trend,
    'todo' => $todo,
    'todoVolume' => $todoVolume,
    'blockers' => $blockers,
    'links' => $links,
]) }}"></div>
@endsection
