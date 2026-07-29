@extends('layouts.eva')

@section('title', 'Article Library')

@section('content')
<div data-react="EvaArticleLibrary" data-props="{{ json_encode([
    'articles' => $articles,
    'tags' => $tags,
    'activeTag' => $activeTag,
    'subjects' => $subjects,
    'services' => $services,
    'stats' => $stats,
]) }}"></div>
@endsection
