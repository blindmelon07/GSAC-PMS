{{-- resources/views/billing/invoice.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $invoice->invoice_number }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1a1a2e; }

  .header { background: #ffffff; padding: 24px 40px; border-bottom: 2px solid #185FA5; }
  .header-row { display: table; width: 100%; }
  .header-left, .header-right { display: table-cell; vertical-align: middle; }
  .header-right { text-align: right; }
  .header-sub { font-size: 10px; color: #666; margin-top: 4px; }
  .invoice-label { font-size: 24px; font-weight: bold; letter-spacing: 2px; color: #185FA5; }
  .invoice-number { font-size: 11px; color: #888; font-family: monospace; margin-top: 3px; }

  .meta-section { padding: 18px 40px; background: #f8f9fc; border-bottom: 1px solid #e8e8e8; }
  .meta-table { width: 100%; }
  .meta-table td { width: 25%; padding-right: 16px; vertical-align: top; }
  .meta-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #888; margin-bottom: 3px; }
  .meta-value { font-size: 12px; font-weight: bold; color: #1a1a2e; }

  .address-section { padding: 18px 40px; border-bottom: 1px solid #e8e8e8; }
  .address-table { width: 100%; }
  .address-table td { width: 50%; vertical-align: top; padding-right: 24px; }
  .address-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #888;
                   border-bottom: 2px solid #185FA5; padding-bottom: 4px; margin-bottom: 8px; }
  .address-name { font-size: 13px; font-weight: bold; color: #1a1a2e; }

  .items-section { padding: 18px 40px; }
  .items-table { width: 100%; border-collapse: collapse; }
  .items-table thead tr { background: #185FA5; color: #fff; }
  .items-table thead th { padding: 9px 12px; font-size: 10px; text-transform: uppercase; text-align: left; }
  .items-table tbody tr:nth-child(even) { background: #f5f7fb; }
  .items-table tbody td { padding: 9px 12px; font-size: 11px; border-bottom: 1px solid #eee; }
  .text-right { text-align: right; }

  .totals-section { padding: 0 40px 20px; text-align: right; }
  .totals-box { display: inline-block; width: 260px; border: 1px solid #dde3f0; border-radius: 6px; overflow: hidden; }
  .totals-row { display: table; width: 100%; padding: 7px 14px; border-bottom: 1px solid #eee; }
  .totals-row:last-child { background: #185FA5; color: #fff; border-bottom: none; }
  .totals-label, .totals-value { display: table-cell; font-size: 11px; }
  .totals-value { text-align: right; font-weight: bold; }

  .footer { margin-top: 24px; padding: 14px 40px; background: #f8f9fc;
            border-top: 1px solid #dde3f0; font-size: 10px; color: #888; text-align: center; }
</style>
</head>
<body>

<div class="header">
  <div class="header-row">
    <div class="header-left">
      <img src="{{ public_path('images/GSACLogo-pdf.png') }}" alt="GSAC" style="height:52px;width:auto;" />
      <div class="header-sub">Main Branch — Supply &amp; Logistics Division</div>
    </div>
    <div class="header-right">
      <div class="invoice-label">INVOICE</div>
      <div class="invoice-number">{{ $invoice->invoice_number }}</div>
    </div>
  </div>
</div>

<div class="meta-section">
  <table class="meta-table"><tr>
    <td><div class="meta-label">Invoice Date</div><div class="meta-value">{{ $invoice->created_at->format('d M Y') }}</div></td>
    <td><div class="meta-label">Billing Period</div><div class="meta-value">{{ $invoice->billing_period }}</div></td>
    <td><div class="meta-label">Due Date</div><div class="meta-value">{{ $invoice->due_date->format('d M Y') }}</div></td>
    <td><div class="meta-label">Status</div><div class="meta-value">{{ strtoupper($invoice->status) }}</div></td>
  </tr></table>
</div>

<div class="address-section">
  <table class="address-table"><tr>
    <td>
      <div class="address-label">Bill From</div>
      <div class="address-name">GSAC — Main Branch</div>
      <div style="font-size:11px;color:#555;margin-top:4px;">Supply &amp; Logistics Division<br>billing@gsac.ph</div>
    </td>
    <td>
      <div class="address-label">Bill To</div>
      <div class="address-name">{{ $branch->name }}</div>
      <div style="font-size:11px;color:#555;margin-top:4px;">
        {{ $branch->code }}@if($branch->city)<br>{{ $branch->city }}@endif
        @if($branch->contact_email)<br>{{ $branch->contact_email }}@endif
      </div>
    </td>
  </tr></table>
</div>

<div class="items-section">
  <table class="items-table">
    <thead>
      <tr>
        <th style="width:110px">Reference</th>
        <th>Form Type</th>
        <th class="text-right" style="width:70px">Qty</th>
        <th class="text-right" style="width:90px">Unit Price</th>
        <th class="text-right" style="width:100px">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($orders as $order)
        @foreach($order->items as $item)
          <tr>
            <td style="font-family:monospace;font-size:10px;color:#666">{{ $order->reference_number }}</td>
            <td>{{ $item->formType->name }}</td>
            <td class="text-right">{{ number_format($item->quantity) }}</td>
            <td class="text-right">₱{{ number_format($item->unit_price, 2) }}</td>
            <td class="text-right"><strong>₱{{ number_format($item->line_total, 2) }}</strong></td>
          </tr>
        @endforeach
      @endforeach
    </tbody>
  </table>
</div>

<div class="totals-section">
  <div class="totals-box">
    <div class="totals-row">
      <span class="totals-label">Subtotal</span>
      <span class="totals-value">₱{{ number_format($invoice->subtotal, 2) }}</span>
    </div>
    <div class="totals-row">
      <span class="totals-label">VAT ({{ number_format($invoice->tax_rate, 0) }}%)</span>
      <span class="totals-value">₱{{ number_format($invoice->tax_amount, 2) }}</span>
    </div>
    <div class="totals-row">
      <span class="totals-label">Total Due</span>
      <span class="totals-value">₱{{ number_format($invoice->total_amount, 2) }}</span>
    </div>
  </div>
</div>

@if($invoice->notes)
  <div style="padding: 0 40px 16px">
    <div style="background:#fffbec;border-left:3px solid #EF9F27;padding:10px 14px;font-size:11px;color:#555">
      <strong>Notes:</strong> {{ $invoice->notes }}
    </div>
  </div>
@endif

<div class="footer">
  Generated by GSAC on {{ $invoice->created_at->format('d M Y H:i') }}
  by {{ $invoice->generatedBy->name ?? 'System' }}.
  Please settle on or before <strong>{{ $invoice->due_date->format('d M Y') }}</strong>.
  Disputes: <strong>billing@gsac.ph</strong>
</div>

</body>
</html>
