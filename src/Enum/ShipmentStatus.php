<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ShipmentStatus: string implements TranslatableInterface
{
    case PENDING = 'PENDING';
    case IN_TRANSIT = 'IN_TRANSIT';
    case DELIVERED = 'DELIVERED';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::PENDING => $translator->trans('shipmentStatus.pending.label', locale: $locale),
            self::IN_TRANSIT => $translator->trans('shipmentStatus.inTransit.label', locale: $locale),
            self::DELIVERED => $translator->trans('shipmentStatus.delivered.label', locale: $locale),
        };
    }
}
