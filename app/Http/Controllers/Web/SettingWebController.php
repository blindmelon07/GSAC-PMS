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
            'settings'           => Setting::orderBy('key')->get(),
            'printerMaintenance' => self::printerMaintenanceStatus(),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'vat_rate'                           => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_rate'                      => ['required', 'numeric', 'min:0', 'max:100'],
            'printer_consumable_maintenance'     => ['boolean'],
            'printer_non_consumable_maintenance' => ['boolean'],
        ]);

        Setting::setValue('vat_rate',      number_format((float) $data['vat_rate'],      2, '.', ''));
        Setting::setValue('discount_rate', number_format((float) $data['discount_rate'], 2, '.', ''));
        Setting::setValue('printer_consumable_maintenance',     ($data['printer_consumable_maintenance']     ?? false) ? '1' : '0');
        Setting::setValue('printer_non_consumable_maintenance', ($data['printer_non_consumable_maintenance'] ?? false) ? '1' : '0');

        return back()->with('success', 'Settings saved successfully.');
    }

    public static function printerMaintenanceStatus(): array
    {
        return [
            'consumable'     => Setting::getValue('printer_consumable_maintenance',     '0') === '1',
            'non_consumable' => Setting::getValue('printer_non_consumable_maintenance', '0') === '1',
        ];
    }
}
