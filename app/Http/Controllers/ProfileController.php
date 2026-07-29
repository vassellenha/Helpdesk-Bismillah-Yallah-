<?php

namespace App\Http\Controllers;

use App\Support\CurrentActor;
use App\Support\ProfilePresenter;
use Illuminate\Http\JsonResponse;

/**
 * "My Profile" — one read-only endpoint per role, each resolving to that
 * role's fixed CurrentActor persona (this mockup has no login session to
 * derive "the current user" from any other way).
 */
class ProfileController extends Controller
{
    public function requester(): JsonResponse
    {
        return response()->json(ProfilePresenter::present(CurrentActor::requester()));
    }

    public function approver(): JsonResponse
    {
        return response()->json(ProfilePresenter::present(CurrentActor::approver()));
    }

    public function support(): JsonResponse
    {
        return response()->json(ProfilePresenter::present(CurrentActor::support()));
    }

    public function supportBpo(): JsonResponse
    {
        return response()->json(ProfilePresenter::present(CurrentActor::supportBpo()));
    }

    public function teamLead(): JsonResponse
    {
        return response()->json(ProfilePresenter::present(CurrentActor::teamLead()));
    }

    public function admin(): JsonResponse
    {
        return response()->json(ProfilePresenter::present(CurrentActor::admin()));
    }

    public function eva(): JsonResponse
    {
        return response()->json(ProfilePresenter::present(CurrentActor::knowledgeAdmin()));
    }
}
