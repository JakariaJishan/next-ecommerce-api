<?php
namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;

class DiscountService
{
    /**
     * Calculate discount for a cart based on coupon
     */
    public function calculateDiscount(Cart $cart, Coupon $coupon): array
    {
        $cartItems = $cart->cartItems()->with('product')->get();
        $subtotal = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });

        // Check minimum order amount
        if ($coupon->min_order_amount && $subtotal < $coupon->min_order_amount) {
            return [
                'success' => false,
                'message' => 'Minimum order amount not met',
                'discount' => 0,
                'subtotal' => $subtotal,
                'final_amount' => $subtotal,
            ];
        }

        $discount = 0;

        switch ($coupon->type) {
            case 'percentage':
                $discount = $subtotal * ($coupon->value / 100);
                break;

            case 'fixed':
                $discount = min($coupon->value, $subtotal); // Don't exceed subtotal
                break;

            case 'bundle':
                $discount = $this->calculateBundleDiscount($cartItems, $coupon);
                break;

            case 'bogo':
                $discount = $this->calculateBogoDiscount($cartItems, $coupon);
                break;
        }

        return [
            'success' => true,
            'message' => 'Discount applied successfully',
            'discount' => $discount,
            'subtotal' => $subtotal,
            'final_amount' => $subtotal - $discount,
        ];
    }

    /**
     * Calculate bundle discount (e.g., "Buy 3 items from category X, get 20% off")
     */
    private function calculateBundleDiscount($cartItems, Coupon $coupon): float
    {
        $conditions = $coupon->conditions;

        if (!isset($conditions['category_id']) || !isset($conditions['min_quantity']) || !isset($conditions['discount_percentage'])) {
            return 0;
        }

        $categoryId = $conditions['category_id'];
        $minQuantity = $conditions['min_quantity'];
        $discountPercentage = $conditions['discount_percentage'];

        // Count items in the specified category
        $categoryItems = $cartItems->filter(function ($item) use ($categoryId) {
            return $item->product->category_id == $categoryId;
        });

        $categoryItemsCount = $categoryItems->sum('quantity');

        if ($categoryItemsCount >= $minQuantity) {
            // Calculate discount on category items
            $categorySubtotal = $categoryItems->sum(function ($item) {
                return $item->product->price * $item->quantity;
            });

            return $categorySubtotal * ($discountPercentage / 100);
        }

        return 0;
    }

    /**
     * Calculate BOGO (Buy One Get One) discount
     */
    private function calculateBogoDiscount($cartItems, Coupon $coupon): float
    {
        $conditions = $coupon->conditions;

        if (!isset($conditions['buy_quantity']) || !isset($conditions['get_quantity']) || !isset($conditions['discount_percentage'])) {
            return 0;
        }

        $buyQuantity = $conditions['buy_quantity'];
        $getQuantity = $conditions['get_quantity'];
        $discountPercentage = $conditions['discount_percentage'];
        $productId = $conditions['product_id'] ?? null;
        $categoryId = $conditions['category_id'] ?? null;

        $eligibleItems = $cartItems;

        // Filter by product or category if specified
        if ($productId) {
            $eligibleItems = $eligibleItems->filter(function ($item) use ($productId) {
                return $item->product_id == $productId;
            });
        } elseif ($categoryId) {
            $eligibleItems = $eligibleItems->filter(function ($item) use ($categoryId) {
                return $item->product->category_id == $categoryId;
            });
        }

        // Sort by price (to discount cheaper items)
        $sortedItems = $eligibleItems->sortBy(function ($item) {
            return $item->product->price;
        });

        $totalQuantity = $sortedItems->sum('quantity');
        $discountSets = floor($totalQuantity / ($buyQuantity + $getQuantity));
        $remainingItems = $totalQuantity % ($buyQuantity + $getQuantity);

        // Calculate how many items get the discount
        $discountedItemsCount = $discountSets * $getQuantity;

        // If there are remaining items and they're more than buyQuantity,
        // some of them can also get the discount
        if ($remainingItems > $buyQuantity) {
            $discountedItemsCount += $remainingItems - $buyQuantity;
        }

        // Calculate discount
        $discount = 0;
        $itemsToDiscount = $discountedItemsCount;

        foreach ($sortedItems as $item) {
            if ($itemsToDiscount <= 0) break;

            $discountableQuantity = min($item->quantity, $itemsToDiscount);
            $discount += ($item->product->price * $discountableQuantity) * ($discountPercentage / 100);
            $itemsToDiscount -= $discountableQuantity;
        }

        return $discount;
    }

    /**
     * Validate if a coupon can be applied by a user
     */
    public function validateCouponForUser(Coupon $coupon, $userId, $cartTotal = null): array
    {
        if (!$coupon->isValid()) {
            return [
                'valid' => false,
                'message' => 'This coupon is no longer valid.'
            ];
        }

        // Check if user has already used this coupon
        $userUsageCount = $coupon->users()->where('user_id', $userId)->count();

        if ($userUsageCount > 0 && !$coupon->conditions['allow_multiple_uses'] ?? false) {
            return [
                'valid' => false,
                'message' => 'You have already used this coupon.'
            ];
        }

        // Check minimum order amount
        if ($cartTotal && $coupon->min_order_amount && $cartTotal < $coupon->min_order_amount) {
            return [
                'valid' => false,
                'message' => "Minimum order amount of {$coupon->min_order_amount} not met."
            ];
        }

        return ['valid' => true, 'message' => 'Coupon is valid'];
    }
}
