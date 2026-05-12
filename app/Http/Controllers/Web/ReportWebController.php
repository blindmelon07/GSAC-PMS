<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FormOrder;
use App\Models\FormOrderItem;
use App\Models\Invoice;
use App\Models\InventoryMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportWebController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $type     = $request->input('type', 'orders');
        $from     = $request->filled('from') ? $request->input('from') : now()->startOfMonth()->toDateString();
        $to       = $request->filled('to')   ? $request->input('to')   : now()->toDateString();
        $branchId = $request->filled('branch_id') ? $request->input('branch_id') : null;

        $results = match ($type) {
            'invoices'    => $this->invoiceReport($from, $to, $branchId),
            'branches'    => $this->branchReport($from, $to),
            'form-types'  => $this->formTypeReport($from, $to, $branchId),
            'audit-logs'  => $this->auditLogReport($from, $to),
            default       => $this->ordersReport($from, $to, $branchId),
        };

        return Inertia::render('Reports', [
            'type'     => $type,
            'from'     => $from,
            'to'       => $to,
            'branchId' => $branchId,
            'results'  => $results['data'],
            'summary'  => $results['summary'],
            /** @psalm-suppress TooFewArguments */
            'branches' => Branch::query()->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $type     = $request->input('type', 'orders');
        $from     = $request->filled('from') ? $request->input('from') : now()->startOfMonth()->toDateString();
        $to       = $request->filled('to')   ? $request->input('to')   : now()->toDateString();
        $branchId = $request->filled('branch_id') ? $request->input('branch_id') : null;

        $results = match ($type) {
            'invoices'    => $this->invoiceReport($from, $to, $branchId),
            'branches'    => $this->branchReport($from, $to),
            'form-types'  => $this->formTypeReport($from, $to, $branchId),
            'audit-logs'  => $this->auditLogReport($from, $to),
            default       => $this->ordersReport($from, $to, $branchId),
        };

        $branch   = $branchId ? Branch::query()->where('id', $branchId)->first() : null;
        $typeLabel = match ($type) {
            'invoices'    => 'Invoices',
            'branches'    => 'Branch Summary',
            'form-types'  => 'Form Types Usage',
            'audit-logs'  => 'Inventory Audit Log',
            default       => 'Orders',
        };

        $filename = "report-{$type}-{$from}-to-{$to}.pdf";

        $logoSrc = $this->resizedLogoDataUri(public_path('images/GSACLogo.png'), 160, 50);

        $pdf = Pdf::loadView('reports.report', [
            'type'        => $type,
            'typeLabel'   => $typeLabel,
            'from'        => $from,
            'to'          => $to,
            'branch'      => $branch,
            'data'        => $results['data'],
            'summary'     => $results['summary'],
            'generatedBy' => $request->user()->name,
            'generatedAt' => now()->format('d M Y H:i'),
            'logoSrc'     => $logoSrc,
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'defaultFont'             => 'DejaVu Sans',
            'isRemoteEnabled'         => false,
            'isHtml5ParserEnabled'    => false,
            'isFontSubsettingEnabled' => true,
            'dpi'                     => 72,
            'enable_php'              => false,
            'enable_javascript'       => false,
            'enable_css_float'        => false,
        ]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    private function resizedLogoDataUri(string $path, int $maxW, int $maxH): string
    {
        if (!file_exists($path) || !function_exists('imagecreatefrompng')) {
            return '';
        }

        [$srcW, $srcH] = getimagesize($path);
        $ratio  = min($maxW / $srcW, $maxH / $srcH);
        $dstW   = (int) round($srcW * $ratio);
        $dstH   = (int) round($srcH * $ratio);

        $src = imagecreatefrompng($path);
        $dst = imagecreatetruecolor($dstW, $dstH);

        // White background (DomPDF handles JPEG faster than transparent PNG)
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        ob_start();
        imagejpeg($dst, null, 85);
        $jpeg = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return 'data:image/jpeg;base64,' . base64_encode($jpeg);
    }

    private function ordersReport(string $from, string $to, ?string $branchId): array
    {
        $query = FormOrder::with(['branch', 'requester'])
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->orderByDesc('created_at');

        if ($branchId) $query->where('branch_id', $branchId);

        $orders = $query->get();

        return [
            'data'    => $orders->map(fn ($o) => [
                'reference'  => $o->reference_number,
                'branch'     => $o->branch?->name,
                'requester'  => $o->requester?->name,
                'status'     => $o->status,
                'priority'   => $o->priority,
                'subtotal'   => (float) $o->subtotal,
                'tax_amount' => (float) $o->tax_amount,
                'total'      => (float) $o->total_amount,
                'date'       => $o->created_at->toDateString(),
            ]),
            'summary' => [
                'count'        => $orders->count(),
                'total_amount' => (float) $orders->sum('total_amount'),
                'total_tax'    => (float) $orders->sum('tax_amount'),
                'by_status'    => $orders->groupBy('status')->map->count(),
            ],
        ];
    }

    private function invoiceReport(string $from, string $to, ?string $branchId): array
    {
        $query = Invoice::with(['branch', 'generatedBy'])
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->orderByDesc('created_at');

        if ($branchId) $query->where('branch_id', $branchId);

        $invoices = $query->get();

        return [
            'data'    => $invoices->map(fn ($i) => [
                'invoice_number'  => $i->invoice_number,
                'branch'          => $i->branch?->name,
                'billing_period'  => $i->billing_period,
                'status'          => $i->status,
                'subtotal'        => (float) $i->subtotal,
                'discount_rate'   => (float) $i->discount_rate,
                'discount_amount' => (float) $i->discount_amount,
                'tax_rate'        => (float) $i->tax_rate,
                'tax_amount'      => (float) $i->tax_amount,
                'total'           => (float) $i->total_amount,
                'due_date'        => $i->due_date?->toDateString(),
                'date'            => $i->created_at->toDateString(),
            ]),
            'summary' => [
                'count'           => $invoices->count(),
                'total_amount'    => (float) $invoices->sum('total_amount'),
                'total_discount'  => (float) $invoices->sum('discount_amount'),
                'total_tax'       => (float) $invoices->sum('tax_amount'),
                'by_status'       => $invoices->groupBy('status')->map->count(),
            ],
        ];
    }

    private function branchReport(string $from, string $to): array
    {
        $branches = Branch::notMain()
            ->withCount(['formOrders as total_orders' => fn ($q) =>
                $q->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ])
            ->withCount(['formOrders as pending_orders' => fn ($q) =>
                $q->pending()->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ])
            ->withCount(['formOrders as delivered_orders' => fn ($q) =>
                $q->delivered()->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ])
            ->withCount(['formOrders as billed_orders' => fn ($q) =>
                $q->billed()->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ])
            ->withSum(['formOrders as total_amount' => fn ($q) =>
                $q->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ], 'total_amount')
            ->orderByDesc('total_orders')
            ->get();

        return [
            'data'    => $branches->map(fn ($b) => [
                'branch'          => $b->name,
                'code'            => $b->code,
                'is_active'       => $b->is_active,
                'total_orders'    => $b->total_orders,
                'pending_orders'  => $b->pending_orders,
                'delivered_orders'=> $b->delivered_orders,
                'billed_orders'   => $b->billed_orders,
                'total_amount'    => (float) ($b->total_amount ?? 0),
            ]),
            'summary' => [
                'count'        => $branches->count(),
                'total_orders' => (int) $branches->sum('total_orders'),
                'total_amount' => (float) $branches->sum('total_amount'),
            ],
        ];
    }

    private function auditLogReport(string $from, string $to): array
    {
        $movements = InventoryMovement::with(['inventory.product', 'performer'])
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->orderByDesc('created_at')
            ->get();

        return [
            'data'    => $movements->map(fn ($m) => [
                'date'            => $m->created_at->format('Y-m-d H:i'),
                'product'         => $m->inventory?->product?->name,
                'product_code'    => $m->inventory?->product?->code,
                'type'            => $m->type,
                'quantity_change' => $m->quantity_change,
                'quantity_before' => $m->quantity_before,
                'quantity_after'  => $m->quantity_after,
                'reference'       => $m->reference,
                'notes'           => $m->notes,
                'performed_by'    => $m->performer?->name,
            ]),
            'summary' => [
                'total'            => $movements->count(),
                'restocks'         => $movements->where('type', 'restock')->count(),
                'adjustments'      => $movements->where('type', 'adjustment')->count(),
                'fulfillments'     => $movements->where('type', 'order_fulfillment')->count(),
                'total_restocked'  => (int) $movements->where('type', 'restock')->sum('quantity_change'),
                'total_fulfilled'  => (int) abs($movements->where('type', 'order_fulfillment')->sum('quantity_change')),
            ],
        ];
    }

    private function formTypeReport(string $from, string $to, ?string $branchId): array
    {
        $query = FormOrderItem::with('formType')
            ->whereHas('order', function ($q) use ($from, $to, $branchId) {
                $q->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
                if ($branchId) $q->where('branch_id', $branchId);
            })
            ->select('form_type_id',
                DB::raw('COUNT(*) as times_ordered'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(line_total) as total_amount'),
                DB::raw('AVG(unit_price) as avg_price')
            )
            ->groupBy('form_type_id')
            ->orderByDesc('total_amount');

        $items = $query->get();

        return [
            'data'    => $items->map(fn ($i) => [
                'form_type'      => $i->formType?->name,
                'code'           => $i->formType?->code,
                'times_ordered'  => (int) $i->times_ordered,
                'total_quantity' => (int) $i->total_quantity,
                'avg_price'      => (float) $i->avg_price,
                'total_amount'   => (float) $i->total_amount,
            ]),
            'summary' => [
                'count'          => $items->count(),
                'total_quantity' => (int) $items->sum('total_quantity'),
                'total_amount'   => (float) $items->sum('total_amount'),
            ],
        ];
    }
}
