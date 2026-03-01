<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum CarrierType: string implements TranslatableInterface
{
    case DHL = 'DHL';
    case UPS = 'UPS';
    case FEDEX = 'FEDEX';
    case GLS = 'GLS';
    case COLISSIMO = 'COLISSIMO';
    case CHRONOPOST = 'CHRONOPOST';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::DHL => $translator->trans('carrierType.dhl.label', locale: $locale),
            self::UPS => $translator->trans('carrierType.ups.label', locale: $locale),
            self::FEDEX => $translator->trans('carrierType.fedex.label', locale: $locale),
            self::GLS => $translator->trans('carrierType.gls.label', locale: $locale),
            self::COLISSIMO => $translator->trans('carrierType.colissimo.label', locale: $locale),
            self::CHRONOPOST => $translator->trans('carrierType.chronopost.label', locale: $locale),
        };
    }
}
