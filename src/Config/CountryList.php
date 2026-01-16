<?php

namespace App\Config;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum CountryList: string implements TranslatableInterface
{
    case FR = "FR";
    case EN = "EN";
    case DE = "DE";
    case ES = "ES";
    case IT = "IT";

    /**
     *
     * @param TranslatorInterface $translator
     * @param string|null $locale
     * @return string
     */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::FR => $translator->trans("country.french.label", locale: $locale),
            self::EN => $translator->trans("country.english.label", locale: $locale),
            self::DE => $translator->trans("country.german.label", locale: $locale),
            self::ES => $translator->trans("country.spanish.label", locale: $locale),
            self::IT => $translator->trans("country.italian.label", locale: $locale),
        };
    }
}
