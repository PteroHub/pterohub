<?php
namespace App\Contracts;
/** Future trusted gateway adapter; no actual checkout handling in v1.4. */
interface PaymentGateway extends PterohubExtension {
    public function createCheckout(string $orderReference, int $amountMinor, string $currency): array;
    public function verifyWebhook(string $rawPayload, array $headers): array;
}
