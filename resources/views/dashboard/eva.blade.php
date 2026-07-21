@extends('layouts.app')

@section('title', 'Knowledge Admin Console')

@section('content')
<div data-react="KnowledgeConsole" data-props="{{ json_encode(['articles' => $articles, 'unanswered' => $unanswered]) }}"></div>
@endsection
