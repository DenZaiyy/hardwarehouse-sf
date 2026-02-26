<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum OrderStatus: string implements TranslatableInterface
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case SHIPPED = 'SHIPPED';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::PENDING => $translator->trans('orderStatus.pending.label', locale: $locale),
            self::PROCESSING => $translator->trans('orderStatus.processing.label', locale: $locale),
            self::SHIPPED => $translator->trans('orderStatus.shipped.label', locale: $locale),
            self::DELIVERED => $translator->trans('orderStatus.delivered.label', locale: $locale),
            self::CANCELLED => $translator->trans('orderStatus.cancelled.label', locale: $locale),
        };
    }
}
