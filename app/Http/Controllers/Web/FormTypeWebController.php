<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FormType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormTypeWebController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('FormTypes', [
            'formTypes' => FormType::withTrashed()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'code'          => ['required', 'string', 'max:20', 'unique:form_types,code'],
            'name'          => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'unit_price'    => ['required', 'numeric', 'min:0'],
            'unit_label'    => ['required', 'string', 'max:50'],
            'minimum_order' => ['required', 'integer', 'min:1'],
            'maximum_order' => ['nullable', 'integer', 'min:1'],
            'is_active'     => ['boolean'],
        ]);

        FormType::create($data);

        return back()->with('success', 'Form type created.');
    }

    public function update(Request $request, FormType $formType)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'unit_price'    => ['required', 'numeric', 'min:0'],
            'unit_label'    => ['required', 'string', 'max:50'],
            'minimum_order' => ['required', 'integer', 'min:1'],
            'maximum_order' => ['nullable', 'integer', 'min:1'],
            'is_active'     => ['boolean'],
        ]);

        $formType->update($data);

        return back()->with('success', 'Form type updated.');
    }
}
