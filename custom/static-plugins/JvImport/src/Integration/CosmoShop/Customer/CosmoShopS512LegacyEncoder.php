<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;

final class CosmoShopS512LegacyEncoder implements LegacyEncoderInterface
{
    private const string PREFIX = 's512##';

    public function getName(): string
    {
        return 'CosmoShopS512';
    }

    public function isPasswordValid(#[\SensitiveParameter] string $password, string $hash): bool
    {
        if (1 !== preg_match('/^(s512##[A-Za-z0-9+\\/]{86}):([A-Za-z0-9_-]{32})$/', $hash, $matches)) {
            return false;
        }

        $digest = $this->digest($password, $matches[2]);

        return hash_equals($matches[1], self::PREFIX.$digest);
    }

    private function digest(#[\SensitiveParameter] string $password, string $salt): string
    {
        $digest = rtrim(base64_encode(hash('sha512', $password.$salt, true)), '=');
        for ($iteration = 0; $iteration < 256; ++$iteration) {
            $digest = rtrim(base64_encode(hash('sha512', $digest, true)), '=');
        }

        return $digest;
    }
}
