@extends('layouts.eva')

@section('title', 'Category & Taxonomy')

@section('content')
<div data-react="EvaTaxonomy" data-props="{{ json_encode([
    'tree' => $tree,
    'tags' => $tags,
    'duplicates' => $duplicates,
    'stats' => $stats,
    'catalogUrl' => $catalogUrl,
]) }}"></div>
@endsection
