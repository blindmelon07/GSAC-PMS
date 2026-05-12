<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function restock(Inventory $inventory, int $quantity, User $performedBy, ?string $notes = null): Inventory
    {
        abort_if($quantity <= 0, 422, 'Restock quantity must be greater than zero.');

        return DB::transaction(function () use ($inventory, $quantity, $performedBy, $notes) {
            $before = $inventory->quantity_on_hand;
            $after  = $before + $quantity;

            $inventory->update(['quantity_on_hand' => $after]);

            InventoryMovement::create([
                'inventory_id'    => $inventory->id,
                'type'            => InventoryMovement::TYPE_RESTOCK,
                'quantity_change' => $quantity,
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'notes'           => $notes,
                'performed_by'    => $performedBy->id,
            ]);

            return $inventory->fresh();
        });
    }

    public function adjust(Inventory $inventory, int $newQuantity, User $performedBy, ?string $notes = null): Inventory
    {
        abort_if($newQuantity < 0, 422, 'Stock quantity cannot be negative.');

        return DB::transaction(function () use ($inventory, $newQuantity, $performedBy, $notes) {
            $before = $inventory->quantity_on_hand;
            $change = $newQuantity - $before;

            $inventory->update(['quantity_on_hand' => $newQuantity]);

            InventoryMovement::create([
                'inventory_id'    => $inventory->id,
                'type'            => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity_change' => $change,
                'quantity_before' => $before,
                'quantity_after'  => $newQuantity,
                'notes'           => $notes,
                'performed_by'    => $performedBy->id,
            ]);

            return $inventory->fresh();
        });
    }

    public function updateLevels(Inventory $inventory, int $reorderLevel, int $reorderQuantity): Inventory
    {
        $inventory->update([
            'reorder_level'    => $reorderLevel,
            'reorder_quantity' => $reorderQuantity,
        ]);

        return $inventory->fresh();
    }

    public function deductForOrder(ProductOrder $order, User $performedBy): void
    {
        DB::transaction(function () use ($order, $performedBy) {
            foreach ($order->items as $item) {
                $inventory = Inventory::where('product_id', $item->product_id)->first();

                if (! $inventory) {
                    continue;
                }

                $before = $inventory->quantity_on_hand;
                $after  = max(0, $before - $item->quantity);

                $inventory->update(['quantity_on_hand' => $after]);

                InventoryMovement::create([
                    'inventory_id'    => $inventory->id,
                    'type'            => InventoryMovement::TYPE_ORDER_FULFILLMENT,
                    'quantity_change' => -($before - $after),
                    'quantity_before' => $before,
                    'quantity_after'  => $after,
                    'reference'       => $order->reference_number,
                    'performed_by'    => $performedBy->id,
                ]);
            }
        });
    }

    public function ensureForAllProducts(): void
    {
        Product::withoutTrashed()->each(function (Product $product) {
            Inventory::firstOrCreate(['product_id' => $product->id]);
        });
    }
}
