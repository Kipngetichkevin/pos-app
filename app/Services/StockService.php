<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    /**
     * Record a stock change. Positive quantity = stock in, negative = stock out.
     * This is the ONLY place in the system that should change stock levels.
     */
    public function move(
        Product $product,
        string $branchId,
        float $quantity,
        string $type,
        string $userId,
        ?float $unitCost = null,
        ?Model $reference = null,
        ?string $reason = null,
        ?string $batchId = null,
    ): StockMovement {
        if ($quantity == 0) {
            throw new InvalidArgumentException('Quantity cannot be zero.');
        }

        return DB::transaction(function () use ($product, $branchId, $quantity, $type, $userId, $unitCost, $reference, $reason, $batchId) {
            $level = StockLevel::where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $level) {
                $level = StockLevel::create([
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'quantity' => 0,
                    'average_cost' => $product->cost_price,
                ]);
            }

            $unitCost ??= ((float) $level->average_cost) ?: (float) $product->cost_price;

            $oldQty = (float) $level->quantity;
            $newQty = $oldQty + $quantity;

            // Weighted average cost, recalculated when stock comes in
            if ($quantity > 0) {
                $existingQty = max($oldQty, 0);
                $existingValue = $existingQty * (float) $level->average_cost;
                $level->average_cost = round(($existingValue + $quantity * $unitCost) / ($existingQty + $quantity), 2);
            }

            $level->quantity = $newQty;
            $level->save();

            return StockMovement::create([
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'batch_id' => $batchId,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'balance_after' => $newQty,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $userId,
                'reason' => $reason,
            ]);
        });
    }
}
