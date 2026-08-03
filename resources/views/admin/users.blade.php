@extends('layouts.admin')

@section('title', __('admin.users.title'))

@section('content')
<div
    data-react="UserManagementConsole"
    data-props="{{ json_encode([
        'users' => $users,
        'roles' => $roles,
        'permissionModules' => $permissionModules,
        'permissionActions' => $permissionActions,
        'unitOrganisasi' => $unitOrganisasi,
    ]) }}"
></div>
@endsection
