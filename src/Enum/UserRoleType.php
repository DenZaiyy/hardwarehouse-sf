<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum UserRoleType: string implements TranslatableInterface
{
    case SUPERADMIN = 'ROLE_SUPER_ADMIN';
    case ADMIN = 'ROLE_ADMIN';
    case USER = 'ROLE_USER';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::SUPERADMIN => $translator->trans('user.roles.superadmin.label', locale: $locale),
            self::ADMIN => $translator->trans('user.roles.admin.label', locale: $locale),
            self::USER => $translator->trans('user.roles.user.label', locale: $locale),
        };
    }
}
