<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Exception;

/** A required reference (sales channel, state machine state, legacy method) is unavailable; the whole run aborts before any write. */
final class CosmoShopOrderConfigurationException extends \RuntimeException
{
}
