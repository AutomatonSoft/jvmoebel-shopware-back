<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;

final readonly class CosmoShopNewsletterRecipientRecordMapper
{
    public function __construct(private string $tokenKey)
    {
        if (strlen($tokenKey) < 32) {
            throw new \InvalidArgumentException('Newsletter token key is too short.');
        }
    }

    /** @param array<string, string|null> $source
     * @return array<string, string>
     */
    public function map(Market $market, array $source): array
    {
        $email = mb_strtolower(trim($source['email'] ?? ''));
        $status = mb_strtolower(trim($source['status'] ?? ''));
        $confirmedAt = trim($source['datum_confirm'] ?? '');

        return [
            'id' => CosmoShopCustomerIdentity::newsletterRecipientId($market, $email),
            'email' => $email,
            'hash' => hash_hmac('sha256', $market->domain()."\0".$email, $this->tokenKey),
            'sales_channel_id' => $market->salesChannelId(),
            'status' => match ($status) {
                'a' => '' !== $confirmedAt && '0000-00-00 00:00:00' !== $confirmedAt ? 'optIn' : 'direct',
                'd' => 'optOut',
                'p' => 'notSet',
                default => 'invalid',
            },
            'salutation' => match (mb_strtolower(trim($source['anrede'] ?? ''))) {
                'm' => 'mr',
                'w' => 'mrs',
                default => 'not_specified',
            },
            'title' => trim($source['anrede_titel'] ?? ''),
            'first_name' => trim($source['vorname'] ?? ''),
            'last_name' => trim($source['nachname'] ?? ''),
            'zip_code' => trim($source['plz'] ?? ''),
            'city' => trim($source['ort'] ?? ''),
            'street' => trim($source['strasse'] ?? ''),
        ];
    }
}
