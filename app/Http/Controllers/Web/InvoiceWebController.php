<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateInvoiceRequest;
use App\Models\Branch;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceWebController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly PdfService $pdfService,
    ) {}

    public function index(Request $request): Response
    {
        $user  = $request->user();
        $query = Invoice::with(['branch', 'generatedBy'])->latest();

        if ($user->isBranchUser()) $query->where('branch_id', $user->branch_id);
        if ($request->filled('status')) $query->where('status', $request->status);

        return Inertia::render('Invoices', [
            'invoices'        => $query->paginate(20)->withQueryString(),
            'billableSummary' => $user->isAdmin() ? $this->invoiceService->getBillableSummary() : null,
            'isAdmin'         => $user->isAdmin(),
            'branches'        => $user->isAdmin() ? Branch::active()->orderBy('code')->get() : null,
        ]);
    }

    public function generate(GenerateInvoiceRequest $request)
    {
        try {
            $invoice = $this->invoiceService->generate($request->validated(), $request->user());
            return redirect('/invoices')->with('success', "Invoice {$invoice->invoice_number} generated successfully.");
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function preview(Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        return $this->pdfService->streamInvoice($invoice->load(['branch', 'orders.items.formType', 'generatedBy']));
    }

    public function download(Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        return $this->pdfService->downloadInvoice($invoice->load(['branch', 'orders.items.formType', 'generatedBy']));
    }

    public function markPaid(Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $invoice->update(['status' => Invoice::STATUS_PAID, 'paid_at' => now()]);
        return back()->with('success', 'Invoice marked as paid.');
    }
}
