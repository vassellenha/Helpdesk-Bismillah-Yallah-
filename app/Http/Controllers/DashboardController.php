<?php

namespace App\Http\Controllers;

use App\Support\DummyData;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function requester(): View
    {
        return view('dashboard.requester', [
            'role' => 'requester',
            'tickets' => DummyData::tickets(),
            'categories' => DummyData::categories(),
            'notifications' => DummyData::notifications(),
        ]);
    }

    public function approver(): View
    {
        return view('dashboard.approver', [
            'role' => 'approver',
            'queue' => DummyData::approvalQueue(),
            'notifications' => DummyData::notifications(),
        ]);
    }

    public function support(): View
    {
        return view('dashboard.support', [
            'role' => 'support',
            'tickets' => DummyData::tickets(),
            'agents' => DummyData::agents(),
            'categories' => DummyData::categories(),
            'notifications' => DummyData::notifications(),
        ]);
    }

    public function teamLead(): View
    {
        return view('dashboard.team-lead', [
            'role' => 'team-lead',
            'agents' => DummyData::agents(),
            'slaPerformance' => DummyData::slaPerformance(),
            'ticketVolume' => DummyData::ticketVolumeByCategory(),
            'notifications' => DummyData::notifications(),
        ]);
    }

    public function eva(): View
    {
        return view('dashboard.eva', [
            'role' => 'eva',
            'articles' => DummyData::knowledgeArticles(),
            'unanswered' => DummyData::unansweredQuestions(),
            'notifications' => DummyData::notifications(),
        ]);
    }
}
