@extends('layouts.eva')

@section('title', 'Log Percakapan')

@section('content')
<div data-react="EvaConversationLog" data-props="{{ json_encode([
    'conversations' => $conversations,
    'stats' => $stats,
    'showing' => $showing,
    'retentionDays' => $retentionDays,
]) }}"></div>
@endsection
