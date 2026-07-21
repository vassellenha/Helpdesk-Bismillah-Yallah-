<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PortalController extends Controller
{
    public function index(): View
    {
        return view('portal.select-role', [
            'roles' => config('helpdesk.roles'),
        ]);
    }
}
