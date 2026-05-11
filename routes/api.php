<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FormOrderController;
use App\Http\Controllers\Api\FormTypeController;
use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    Route::get('/me', fn (Request $r) => $r->user()->load('branch'));

    // Reference data
    Route::get('/branches',              [BranchController::class, 'index']);
    Route::get('/branches/{branch}',     [BranchController::class, 'show']);
    Route::get('/form-types',            [FormTypeController::class, 'index']);
    Route::get('/form-types/{formType}', [FormTypeController::class, 'show']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Form orders CRUD + workflow
    Route::apiResource('form-orders', FormOrderController::class);
    Route::patch('/form-orders/{form_order}/approve', [FormOrderController::class, 'approve']);
    Route::patch('/form-orders/{form_order}/reject',  [FormOrderController::class, 'reject']);
    Route::patch('/form-orders/{form_order}/deliver', [FormOrderController::class, 'deliver']);

    // Invoices / billing
    Route::get('/invoices',                       [InvoiceController::class, 'index']);
    Route::post('/invoices/generate',             [InvoiceController::class, 'generate']);
    Route::get('/invoices/billable-summary',      [InvoiceController::class, 'billableSummary']);
    Route::get('/invoices/{invoice}',             [InvoiceController::class, 'show']);
    Route::get('/invoices/{invoice}/download',    [InvoiceController::class, 'download']);
    Route::get('/invoices/{invoice}/preview',     [InvoiceController::class, 'preview']);
    Route::patch('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid']);
});
