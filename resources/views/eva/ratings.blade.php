@extends('layouts.eva')

@section('title', 'Rating & Feedback')

@section('content')
<div data-react="EvaRatingFeedback" data-props="{{ json_encode([
    'summary' => $summary,
    'distribution' => $distribution,
    'sources' => $sources,
    'comments' => $comments,
    'helpfulThreshold' => $helpfulThreshold,
]) }}"></div>
@endsection
