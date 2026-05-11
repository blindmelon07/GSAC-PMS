<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchWebController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $branches = Branch::withCount('users')->orderBy('code')->get();

        return Inertia::render('Branches', [
            'branches' => $branches,
        ]);
    }
}
