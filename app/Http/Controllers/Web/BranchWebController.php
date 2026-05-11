<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchWebController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Branches', [
            'branches' => Branch::withCount('users')->orderBy('code')->get(),
            'isAdmin'  => $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'code'            => ['required', 'string', 'max:20', 'unique:branches,code'],
            'name'            => ['required', 'string', 'max:255'],
            'address'         => ['nullable', 'string', 'max:500'],
            'city'            => ['nullable', 'string', 'max:100'],
            'contact_person'  => ['nullable', 'string', 'max:255'],
            'contact_email'   => ['nullable', 'email', 'max:255'],
            'contact_phone'   => ['nullable', 'string', 'max:50'],
            'is_active'       => ['boolean'],
        ]);

        Branch::create($data);

        return back()->with('success', 'Branch created.');
    }

    public function update(Request $request, Branch $branch)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'address'         => ['nullable', 'string', 'max:500'],
            'city'            => ['nullable', 'string', 'max:100'],
            'contact_person'  => ['nullable', 'string', 'max:255'],
            'contact_email'   => ['nullable', 'email', 'max:255'],
            'contact_phone'   => ['nullable', 'string', 'max:50'],
            'is_active'       => ['boolean'],
        ]);

        $branch->update($data);

        return back()->with('success', 'Branch updated.');
    }
}
