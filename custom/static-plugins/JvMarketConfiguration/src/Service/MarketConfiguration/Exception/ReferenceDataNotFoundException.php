<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Service\MarketConfiguration\Exception;

final class ReferenceDataNotFoundException extends \RuntimeException
{
    public static function forValue(string $entity, string $field, string $value): self
    {
        return new self(sprintf('Required %s with %s "%s" was not found.', $entity, $field, $value));
    }
}
