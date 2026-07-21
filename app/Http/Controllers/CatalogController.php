<?php

namespace App\Http\Controllers;

use App\Models\IssueCategory;
use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Whole active service catalog in one payload — small enough (13
     * services / ~40 subcategories / 133 subjects) that the New Ticket
     * modal filters it client-side instead of round-tripping per cascade
     * step.
     */
    public function tree(): JsonResponse
    {
        return response()->json([
            'services' => ServiceCatalogService::orderBy('name')->get(['id', 'name']),
            'subcategories' => ServiceCatalogSubcategory::orderBy('name')->get(['id', 'service_id', 'name']),
            'subjects' => ServiceCatalogSubject::where('is_active', true)
                ->with('issueCategory:id,name')
                ->orderBy('name')
                ->get(['id', 'service_id', 'subcategory_id', 'issue_category_id', 'name', 'requires_approval'])
                ->map(fn (ServiceCatalogSubject $s) => [
                    'id' => $s->id,
                    'service_id' => $s->service_id,
                    'subcategory_id' => $s->subcategory_id,
                    'name' => $s->name,
                    'issue_category' => $s->issueCategory->name,
                    'requires_approval' => $s->requires_approval,
                ]),
            'issueCategories' => IssueCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function approvers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $approvers = Role::where('name', 'Approver')->firstOrFail()
            ->users()
            ->where('status', 'active')
            ->when($q !== '', fn ($query) => $query->where('users.name', 'like', "%{$q}%"))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.jabatan', 'users.unit']);

        return response()->json($approvers);
    }
}
