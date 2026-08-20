@extends('layouts.admin')

@section('title', __('admin.users.title'))

@section('content')
<div
    data-react="UserManagementConsole"
    data-props="{{ json_encode([
        'users' => $users,
        'usersMeta' => $usersMeta,
        'userStats' => $userStats,
        'listUrl' => route('admin.users.list'),
        'roles' => $roles,
        'permissionModules' => $permissionModules,
        'permissionActions' => $permissionActions,
        'unitOrganisasi' => $unitOrganisasi,
        'jabatanOptions' => $jabatanOptions,
        'exportUrl' => $exportUrl,
        'filterOptionsUrl' => $filterOptionsUrl,
        'importUrl' => $importUrl,
    ]) }}"
></div>
@endsection
