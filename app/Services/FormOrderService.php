<?php

namespace App\Services;

use App\Models\FormOrder;
use App\Models\FormOrderItem;
use App\Models\FormType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FormOrderService
{
    public function create(array $data, User $requestedBy): FormOrder
    {
        return DB::transaction(function () use ($data, $requestedBy) {
            $order = FormOrder::create([
                'branch_id'    => $data['branch_id'],
                'requested_by' => $requestedBy->id,
                'priority'     => $data['priority'] ?? FormOrder::PRIORITY_NORMAL,
                'notes'        => $data['notes'] ?? null,
                'needed_by'    => $data['needed_by'] ?? null,
                'status'       => FormOrder::STATUS_PENDING,
            ]);

            $this->syncItems($order, $data['items']);
            $order->recalculateTotals();

            return $order->fresh(['items.formType', 'branch', 'requester']);
        });
    }

    public function approve(FormOrder $order, User $approvedBy): FormOrder
    {
        abort_if(! $order->canBeApproved(), 422, 'This order cannot be approved in its current state.');

        $order->update([
            'status'      => FormOrder::STATUS_APPROVED,
            'approved_by' => $approvedBy->id,
            'approved_at' => now(),
        ]);

        return $order->fresh();
    }

    public function reject(FormOrder $order, User $rejectedBy, string $reason = ''): FormOrder
    {
        abort_if(! $order->canBeRejected(), 422, 'This order cannot be rejected in its current state.');

        $order->update([
            'status'           => FormOrder::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by'      => $rejectedBy->id,
            'approved_at'      => now(),
        ]);

        return $order->fresh();
    }

    public function deliver(FormOrder $order, User $deliveredBy): FormOrder
    {
        abort_if(! $order->canBeDelivered(), 422, 'This order cannot be marked delivered in its current state.');

        $order->update([
            'status'       => FormOrder::STATUS_DELIVERED,
            'delivered_by' => $deliveredBy->id,
            'delivered_at' => now(),
        ]);

        return $order->fresh();
    }

    public function bill(FormOrder $order, User $billedBy, int $invoiceId): FormOrder
    {
        abort_if(! $order->canBeBilled(), 422, 'Only delivered orders can be billed.');

        $order->update([
            'status'     => FormOrder::STATUS_BILLED,
            'billed_by'  => $billedBy->id,
            'billed_at'  => now(),
            'invoice_id' => $invoiceId,
        ]);

        return $order->fresh();
    }

    private function syncItems(FormOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $formType    = FormType::findOrFail($item['form_type_id']);
            $printerType = $item['printer_type'] ?? 'consumable';
            $unitPrice   = $formType->priceFor($printerType);

            FormOrderItem::create([
                'form_order_id' => $order->id,
                'form_type_id'  => $formType->id,
                'printer_type'  => $printerType,
                'quantity'      => $item['quantity'],
                'unit_price'    => $unitPrice,
                'line_total'    => $unitPrice * $item['quantity'],
                'notes'         => $item['notes'] ?? null,
            ]);
        }
    }
}
