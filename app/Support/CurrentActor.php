<?php

namespace App\Support;

use App\Models\User;

/**
 * This mockup has no login/auth flow — every admin screen acts as the same
 * seeded Administrator. Audit trail rows need a real actor_id FK, so this
 * resolves that single persona to its actual users row instead of the
 * display-only DummyData::currentAdmin() array.
 */
class CurrentActor
{
    public static function admin(): User
    {
        return User::where('email', 'marcell.laforteza@adhi.co.id')->firstOrFail();
    }

    public static function requester(): User
    {
        return User::where('email', 'andi.pratama@adhi.co.id')->firstOrFail();
    }

    public static function approver(): User
    {
        return User::where('email', 'karina.putri@adhi.co.id')->firstOrFail();
    }
}
