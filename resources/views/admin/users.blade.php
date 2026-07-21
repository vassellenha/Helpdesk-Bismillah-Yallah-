@extends('layouts.admin')

@section('title', 'User & Role Management')

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
