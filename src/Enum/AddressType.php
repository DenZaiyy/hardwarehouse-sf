<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum AddressType: string implements TranslatableInterface
{
    case DELIVERY = 'DELIVERY';
    case BILLING = 'BILLING';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::DELIVERY => $translator->trans('addresstype.delivery.label', locale: $locale),
            self::BILLING => $translator->trans('addresstype.billing.label', locale: $locale),
        };
    }
}
