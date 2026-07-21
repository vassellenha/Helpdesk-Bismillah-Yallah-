@extends('layouts.app')

@section('title', 'Team Lead Dashboard')

@section('content')
<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div data-react="SlaChart" data-props="{{ json_encode(['data' => $slaPerformance]) }}"></div>
    <div data-react="CategoryChart" data-props="{{ json_encode(['data' => $ticketVolume]) }}"></div>
</div>

<div data-react="AgentsPanel" data-props="{{ json_encode(['agents' => $agents]) }}"></div>
@endsection
