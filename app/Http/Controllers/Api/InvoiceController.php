<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly PdfService $pdfService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $user  = $request->user();
        $query = Invoice::with(['branch', 'generatedBy'])->latest();

        if ($user->isBranchUser()) $query->where('branch_id', $user->branch_id);
        $request->whenFilled('status', fn ($v) => $query->where('status', $v));
        if ($request->filled('branch_id') && $user->isAdmin()) $query->where('branch_id', $request->branch_id);

        return response()->json($query->paginate(20));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);
        return response()->json($invoice->load(['branch', 'generatedBy', 'orders.items.formType']));
    }

    public function generate(GenerateInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->generate($request->validated(), $request->user());
        return response()->json([
            'message' => "Invoice {$invoice->invoice_number} generated successfully.",
            'data'    => $invoice,
        ], 201);
    }

    public function download(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        return $this->pdfService->downloadInvoice($invoice->load(['branch', 'orders.items.formType', 'generatedBy']));
    }

    public function preview(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        return $this->pdfService->streamInvoice($invoice->load(['branch', 'orders.items.formType', 'generatedBy']));
    }

    public function markPaid(Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);
        $invoice->update(['status' => Invoice::STATUS_PAID, 'paid_at' => now()]);
        return response()->json(['message' => 'Invoice marked as paid.', 'data' => $invoice->fresh()]);
    }

    public function billableSummary(): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);
        return response()->json($this->invoiceService->getBillableSummary());
    }
}
