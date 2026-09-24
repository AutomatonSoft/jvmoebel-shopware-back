<?php declare(strict_types=1);

namespace Jv\Promotion\Service\Promotion;

final readonly class JvManagedPromotionMatch
{
    public function __construct(
        public string $promotionId,
        public float $discountPercent,
    ) {
    }
}
