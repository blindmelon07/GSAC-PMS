<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductWebController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Products', [
            'products' => Product::withTrashed()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'code'            => ['required', 'string', 'max:20', 'unique:products,code'],
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'category'        => ['required', 'string', 'in:' . implode(',', Product::CATEGORIES)],
            'unit_price'      => ['required', 'numeric', 'min:0'],
            'unit_label'      => ['required', 'string', 'max:50'],
            'minimum_order'   => ['required', 'integer', 'min:1'],
            'maximum_order'   => ['nullable', 'integer', 'min:1'],
            'customizations'          => ['nullable', 'array'],
            'customizations.*.key'    => ['required', 'string'],
            'customizations.*.label'  => ['required', 'string'],
            'customizations.*.type'   => ['required', 'string', 'in:select,text'],
            'customizations.*.options' => ['required_if:customizations.*.type,select', 'array'],
            'is_active'       => ['boolean'],
        ]);

        Product::create($data);

        return back()->with('success', 'Product created.');
    }

    public function update(Request $request, Product $product)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'category'        => ['required', 'string', 'in:' . implode(',', Product::CATEGORIES)],
            'unit_price'      => ['required', 'numeric', 'min:0'],
            'unit_label'      => ['required', 'string', 'max:50'],
            'minimum_order'   => ['required', 'integer', 'min:1'],
            'maximum_order'   => ['nullable', 'integer', 'min:1'],
            'customizations'          => ['nullable', 'array'],
            'customizations.*.key'    => ['required', 'string'],
            'customizations.*.label'  => ['required', 'string'],
            'customizations.*.type'   => ['required', 'string', 'in:select,text'],
            'customizations.*.options' => ['required_if:customizations.*.type,select', 'array'],
            'is_active'       => ['boolean'],
        ]);

        $product->update($data);

        return back()->with('success', 'Product updated.');
    }
}
