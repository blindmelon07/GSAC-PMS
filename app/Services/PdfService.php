<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    public function generateInvoice(Invoice $invoice): string
    {
        $pdf = Pdf::loadView('billing.invoice', [
            'invoice' => $invoice,
            'branch'  => $invoice->branch,
            'orders'  => $invoice->orders->load('items.formType'),
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'defaultFont'          => 'DejaVu Sans',
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
            'dpi'                  => 150,
        ]);

        $filename = $invoice->invoice_number . '.pdf';
        $path     = 'invoices/' . $filename;

        Storage::put($path, $pdf->output());

        return $path;
    }

    public function streamInvoice(Invoice $invoice): \Illuminate\Http\Response
    {
        return Pdf::loadView('billing.invoice', [
            'invoice' => $invoice,
            'branch'  => $invoice->branch,
            'orders'  => $invoice->orders->load('items.formType'),
        ])
        ->setPaper('a4', 'portrait')
        ->stream($invoice->invoice_number . '.pdf');
    }

    public function downloadInvoice(Invoice $invoice): \Illuminate\Http\Response
    {
        return Pdf::loadView('billing.invoice', [
            'invoice' => $invoice,
            'branch'  => $invoice->branch,
            'orders'  => $invoice->orders->load('items.formType'),
        ])
        ->setPaper('a4', 'portrait')
        ->download($invoice->invoice_number . '.pdf');
    }
}
