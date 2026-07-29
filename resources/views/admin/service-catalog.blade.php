@extends('layouts.admin')

@section('title', 'Service Catalog Management')

@section('content')
<nav class="mb-3 text-sm text-gray-400 dark:text-ink-3">
    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-600 dark:hover:text-ink-2">Dashboard Administrator</a>
    <span class="mx-1">›</span>
    <span class="font-medium text-gray-600 dark:text-ink-2">Service Catalog Management</span>
</nav>

<div
    data-react="ServiceCatalogConsole"
    data-props="{{ json_encode([
        'subjects' => $subjects,
        'issueCategories' => $issueCategories,
        'services' => $services,
        'subcategories' => $subcategories,
        'supportAgents' => $supportAgents,
    ]) }}"
></div>
@endsection
