@extends('layouts.eva')

@section('title', 'Unanswered Questions')

@section('content')
<div data-react="EvaUnansweredQuestions" data-props="{{ json_encode([
    'gaps' => $gaps,
    'closed' => $closed,
    'threshold' => $threshold,
    'endpoints' => $endpoints,
    'links' => $links,
]) }}"></div>
@endsection
