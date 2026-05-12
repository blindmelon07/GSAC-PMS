<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductOrderService
{
    public function create(array $data, User $requestedBy): ProductOrder
    {
        return DB::transaction(function () use ($data, $requestedBy) {
            $order = ProductOrder::create([
                'branch_id'    => $requestedBy->branch_id,
                'requested_by' => $requestedBy->id,
                'priority'     => $data['priority'] ?? ProductOrder::PRIORITY_NORMAL,
                'notes'        => $data['notes'] ?? null,
                'needed_by'    => $data['needed_by'] ?? null,
                'status'       => ProductOrder::STATUS_PENDING,
            ]);

            $this->syncItems($order, $data['items']);
            $order->load('items');
            $order->recalculateTotals();

            return $order->fresh(['items.product', 'branch', 'requester']);
        });
    }

    public function approve(ProductOrder $order, User $approvedBy): ProductOrder
    {
        abort_if(! $order->canBeApproved(), 422, 'This order cannot be approved in its current state.');

        $order->update([
            'status'      => ProductOrder::STATUS_APPROVED,
            'approved_by' => $approvedBy->id,
            'approved_at' => now(),
        ]);

        return $order->fresh();
    }

    public function reject(ProductOrder $order, User $rejectedBy, string $reason = ''): ProductOrder
    {
        abort_if(! $order->canBeRejected(), 422, 'This order cannot be rejected in its current state.');

        $order->update([
            'status'           => ProductOrder::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by'      => $rejectedBy->id,
            'approved_at'      => now(),
        ]);

        return $order->fresh();
    }

    public function deliver(ProductOrder $order, User $deliveredBy): ProductOrder
    {
        abort_if(! $order->canBeDelivered(), 422, 'This order cannot be marked delivered in its current state.');

        $order->update([
            'status'       => ProductOrder::STATUS_DELIVERED,
            'delivered_by' => $deliveredBy->id,
            'delivered_at' => now(),
        ]);

        return $order->fresh();
    }

    private function syncItems(ProductOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);

            ProductOrderItem::create([
                'product_order_id' => $order->id,
                'product_id'       => $product->id,
                'quantity'         => $item['quantity'],
                'unit_price'       => $product->unit_price,
                'line_total'       => $product->unit_price * $item['quantity'],
                'customizations'   => $item['customizations'] ?? null,
                'notes'            => $item['notes'] ?? null,
            ]);
        }
    }
}
