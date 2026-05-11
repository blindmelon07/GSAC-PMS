<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingWebController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Settings', [
            'settings' => Setting::orderBy('key')->get(),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'vat_rate'      => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            Setting::setValue($key, number_format((float) $value, 2, '.', ''));
        }

        return back()->with('success', 'Settings saved successfully.');
    }
}
