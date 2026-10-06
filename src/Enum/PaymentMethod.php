<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Moyens de paiement proposés à l'étape paiement, tous encaissés par Stripe Checkout : les valeurs sont
 * des types de moyens de paiement Stripe (payment_method_types).
 */
enum PaymentMethod: string implements TranslatableInterface
{
    case CARD = 'card';
    case PAYPAL = 'paypal';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::CARD => $translator->trans('paymentMethod.card.label', locale: $locale),
            self::PAYPAL => $translator->trans('paymentMethod.paypal.label', locale: $locale),
        };
    }
}
