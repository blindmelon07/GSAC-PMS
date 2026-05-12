<?php

use App\Http\Controllers\Web\BranchWebController;
use App\Http\Controllers\Web\DashboardWebController;
use App\Http\Controllers\Web\FormTypeWebController;
use App\Http\Controllers\Web\InvoiceWebController;
use App\Http\Controllers\Web\OrderWebController;
use App\Http\Controllers\Web\ProfileWebController;
use App\Http\Controllers\Web\ReportWebController;
use App\Http\Controllers\Web\SettingWebController;
use App\Http\Controllers\Web\UserWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dashboard'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardWebController::class);

    Route::get('/orders',                     [OrderWebController::class, 'index']);
    Route::post('/orders',                    [OrderWebController::class, 'store']);
    Route::patch('/orders/{formOrder}/approve', [OrderWebController::class, 'approve']);
    Route::patch('/orders/{formOrder}/reject',  [OrderWebController::class, 'reject']);
    Route::patch('/orders/{formOrder}/deliver', [OrderWebController::class, 'deliver']);

    Route::get('/invoices',                          [InvoiceWebController::class, 'index']);
    Route::post('/invoices/generate',                [InvoiceWebController::class, 'generate']);
    Route::get('/invoices/{invoice}/preview',        [InvoiceWebController::class, 'preview']);
    Route::get('/invoices/{invoice}/download',       [InvoiceWebController::class, 'download']);
    Route::patch('/invoices/{invoice}/mark-paid',    [InvoiceWebController::class, 'markPaid']);

    Route::get('/branches',              [BranchWebController::class, 'index']);
    Route::post('/branches',             [BranchWebController::class, 'store']);
    Route::patch('/branches/{branch}',   [BranchWebController::class, 'update']);

    Route::post('/profile/password', [ProfileWebController::class, 'changePassword']);

    Route::get('/reports',        [ReportWebController::class, 'index']);
    Route::get('/reports/export', [ReportWebController::class, 'export']);

    Route::get('/settings',  [SettingWebController::class, 'index']);
    Route::post('/settings', [SettingWebController::class, 'update']);

    Route::get('/users',           [UserWebController::class, 'index']);
    Route::post('/users',          [UserWebController::class, 'store']);
    Route::patch('/users/{user}',  [UserWebController::class, 'update']);

    Route::get('/form-types',              [FormTypeWebController::class, 'index']);
    Route::post('/form-types',             [FormTypeWebController::class, 'store']);
    Route::patch('/form-types/{formType}', [FormTypeWebController::class, 'update']);
});

// Auth routes (login/logout) via Sanctum session or simple form auth
Route::get('/login', fn () => inertia('Login'))->name('login');
Route::post('/login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->only('email', 'password');
    if (\Illuminate\Support\Facades\Auth::attempt($credentials, $request->boolean('remember'))) {
        $request->session()->regenerate();
        return redirect('/dashboard');
    }
    return back()->withErrors(['email' => 'Invalid credentials.']);
});
Route::post('/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout');
