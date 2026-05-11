<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Branch::query();

        if (! $request->user()->isAdmin()) {
            $query->active();
        }

        $request->whenFilled('search', fn ($v) => $query->where('name', 'like', "%{$v}%"));

        return response()->json($query->orderBy('code')->get());
    }

    public function show(Branch $branch): JsonResponse
    {
        return response()->json($branch->load(['users']));
    }
}
