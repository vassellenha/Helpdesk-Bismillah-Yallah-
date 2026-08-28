@extends('layouts.eva')

@section('title', 'Documents')

@section('content')
<div data-react="EvaDocuments" data-props="{{ json_encode([
    'documents' => $documents,
    'tags' => $tags,
    'activeTag' => $activeTag,
    'subjects' => $subjects,
    'extensions' => $extensions,
    'readableExtensions' => $readableExtensions,
    'imageExtensions' => $imageExtensions,
]) }}"></div>
@endsection
