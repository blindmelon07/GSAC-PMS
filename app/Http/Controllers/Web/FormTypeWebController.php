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
            'price_consumable'     => ['required', 'numeric', 'min:0'],
            'price_non_consumable' => ['required', 'numeric', 'min:0'],
            'unit_label'           => ['required', 'string', 'max:50'],
            'minimum_order'        => ['required', 'integer', 'min:1'],
            'maximum_order'        => ['nullable', 'integer', 'min:1'],
            'is_active'            => ['boolean'],
        ]);

        // Keep unit_price in sync with consumable price for backward compatibility
        $data['unit_price'] = $data['price_consumable'];

        FormType::create($data);

        return back()->with('success', 'Form type created.');
    }

    public function update(Request $request, FormType $formType)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'price_consumable'     => ['required', 'numeric', 'min:0'],
            'price_non_consumable' => ['required', 'numeric', 'min:0'],
            'unit_label'           => ['required', 'string', 'max:50'],
            'minimum_order'        => ['required', 'integer', 'min:1'],
            'maximum_order'        => ['nullable', 'integer', 'min:1'],
            'is_active'            => ['boolean'],
        ]);

        $data['unit_price'] = $data['price_consumable'];

        $formType->update($data);

        return back()->with('success', 'Form type updated.');
    }
}
