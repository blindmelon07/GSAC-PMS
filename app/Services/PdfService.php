<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfService
{
    private array $options = [
        'defaultFont'          => 'DejaVu Sans',
        'isRemoteEnabled'      => false,
        'isHtml5ParserEnabled' => false,   // XML parser is significantly faster
        'isFontSubsettingEnabled' => true, // smaller file, faster render
        'dpi'                  => 96,      // screen-quality is fine for invoices
    ];

    public function generateInvoice(Invoice $invoice): string
    {
        $path = 'invoices/' . $invoice->invoice_number . '.pdf';

        // Return cached file if it already exists
        if (Storage::exists($path)) {
            return $path;
        }

        $pdf = $this->buildPdf($invoice);
        Storage::put($path, $pdf->output());

        return $path;
    }

    public function streamInvoice(Invoice $invoice): StreamedResponse
    {
        $path = 'invoices/' . $invoice->invoice_number . '.pdf';

        // Serve from storage if already generated
        if (Storage::exists($path)) {
            return $this->serveFromStorage($path, $invoice->invoice_number . '.pdf', 'inline');
        }

        return response()->streamDownload(function () use ($invoice) {
            echo $this->buildPdf($invoice)->output();
        }, $invoice->invoice_number . '.pdf', [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $invoice->invoice_number . '.pdf"',
        ]);
    }

    public function downloadInvoice(Invoice $invoice): StreamedResponse
    {
        $path = 'invoices/' . $invoice->invoice_number . '.pdf';

        // Serve from storage if already generated
        if (Storage::exists($path)) {
            return $this->serveFromStorage($path, $invoice->invoice_number . '.pdf', 'attachment');
        }

        return response()->streamDownload(function () use ($invoice) {
            echo $this->buildPdf($invoice)->output();
        }, $invoice->invoice_number . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function buildPdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('billing.invoice', [
            'invoice' => $invoice->loadMissing(['branch', 'orders.items.formType', 'generatedBy']),
            'branch'  => $invoice->branch,
            'orders'  => $invoice->orders,
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions($this->options);
    }

    private function serveFromStorage(string $path, string $filename, string $disposition): StreamedResponse
    {
        return response()->streamDownload(function () use ($path) {
            echo Storage::get($path);
        }, $filename, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Content-Length'      => Storage::size($path),
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }
}
