@extends('layouts.eva')

@section('title', 'Analytics')

@section('content')
<div data-react="EvaAnalytics" data-props="{{ json_encode([
    'summary' => $summary,
    'trend' => $trend,
    'topQuestions' => $topQuestions,
    'topMaterials' => $topMaterials,
    'links' => $links,
]) }}"></div>
@endsection
