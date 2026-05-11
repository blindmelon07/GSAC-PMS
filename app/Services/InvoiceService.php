<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\FormOrder;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly FormOrderService $orderService,
    ) {}

    public function generate(array $data, User $generatedBy): Invoice
    {
        $branch      = Branch::findOrFail($data['branch_id']);
        $periodStart = Carbon::parse($data['period_start'])->startOfDay();
        $periodEnd   = Carbon::parse($data['period_end'])->endOfDay();

        $orders = FormOrder::delivered()
            ->forBranch($branch->id)
            ->whereBetween('delivered_at', [$periodStart, $periodEnd])
            ->whereNull('invoice_id')
            ->with(['items.formType'])
            ->get();

        abort_if($orders->isEmpty(), 422, 'No unbilled delivered orders found for this branch in the selected period.');

        return DB::transaction(function () use ($branch, $orders, $data, $generatedBy, $periodStart, $periodEnd) {
            $subtotal       = $orders->sum('subtotal');
            $taxRate        = (float) Setting::getValue('vat_rate', 12.00);
            $discountRate   = (float) Setting::getValue('discount_rate', 0.00);
            $discountAmount = round($subtotal * ($discountRate / 100), 2);
            $taxableAmount  = $subtotal - $discountAmount;
            $taxAmount      = round($taxableAmount * ($taxRate / 100), 2);
            $totalAmount    = $taxableAmount + $taxAmount;
            $dueDate        = now()->addDays($data['due_days'] ?? 30);

            $invoice = Invoice::create([
                'branch_id'       => $branch->id,
                'generated_by'    => $generatedBy->id,
                'billing_period'  => $periodStart->format('F Y'),
                'period_start'    => $periodStart->toDateString(),
                'period_end'      => $periodEnd->toDateString(),
                'due_date'        => $dueDate->toDateString(),
                'status'          => Invoice::STATUS_DRAFT,
                'subtotal'        => $subtotal,
                'discount_rate'   => $discountRate,
                'discount_amount' => $discountAmount,
                'tax_rate'        => $taxRate,
                'tax_amount'      => $taxAmount,
                'total_amount'    => $totalAmount,
                'notes'           => $data['notes'] ?? null,
            ]);

            foreach ($orders as $order) {
                $this->orderService->bill($order, $generatedBy, $invoice->id);
            }

            $pdfPath = $this->pdfService->generateInvoice(
                $invoice->fresh(['branch', 'orders.items.formType', 'generatedBy'])
            );
            $invoice->update(['pdf_path' => $pdfPath]);

            return $invoice->fresh(['branch', 'orders']);
        });
    }

    public function getBillableSummary(): Collection
    {
        return Branch::active()
            ->notMain()
            ->withCount(['formOrders as delivered_orders_count' => fn ($q) =>
                $q->delivered()->whereNull('invoice_id')
            ])
            ->withSum(['formOrders as billable_subtotal' => fn ($q) =>
                $q->delivered()->whereNull('invoice_id')
            ], 'subtotal')
            ->having('delivered_orders_count', '>', 0)
            ->get();
    }
}
