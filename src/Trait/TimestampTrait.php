<?php

namespace App\Trait;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use IntlDateFormatter;

trait TimestampTrait
{
    #[ORM\Column]
    private ?DateTimeImmutable $created_at;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updated_at = null;

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    #[ORM\PrePersist]
    // fonction déclenchée avant la persistance
    public function onPrePersist(): void
    {
        $this->created_at = new DateTimeImmutable("now", new DateTimeZone('Europe/Paris'));
    }

    #[ORM\PreUpdate]
    // fonction déclenchée avant la mise à jour
    public function onPreUpdate(): void
    {
        $this->updated_at = new DateTimeImmutable("now", new DateTimeZone('Europe/Paris'));
    }

    /**
     * Retourne un DataTime sous forme de chaine de caractères
     * formatée en fonction de $locale & $timeZone
     * @param DateTimeImmutable|null $aDateTime | null
     * @param string $pattern | "eeee d MMMM yyyy"
     * @param string $separator | " "
     * @param string $locale | "fr_FR"
     * @param string $timeZone | "Europe/Paris"
     * @return string
     */
    public function formatDateTime(?DateTimeImmutable $aDateTime = null, string $pattern = "eeee d MMMM yyyy", string $separator = " ", string $locale = "fr_FR", string $timeZone = "Europe/Paris"): string
    {
        if ($aDateTime === null) {
            $aDateTime = new DateTimeImmutable();
        }

        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT, $timeZone);

        // Sécurité : vérifier le retour de setPattern (bool)
        if (!$formatter->setPattern($pattern)) {
            return '';
        }

        $strDate = $formatter->format($aDateTime);

        if (!is_string($strDate) || trim($strDate) === $pattern) {
            return '';
        }

        $formatted = preg_replace("/( |-|\/)/", $separator, $strDate);

        return is_string($formatted) ? ucwords($formatted) : '';
    }
}
