@extends('layouts.eva')

@section('title', 'Manage FAQ')

@section('content')
<div data-react="EvaFaqManager" data-props="{{ json_encode([
    'faqs' => $faqs,
    'tags' => $tags,
    'activeTag' => $activeTag,
    'prefillQuestion' => $prefillQuestion,
    'subjects' => $subjects,
    'services' => $services,
    'stats' => $stats,
]) }}"></div>
@endsection
