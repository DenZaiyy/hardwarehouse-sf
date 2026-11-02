<?php

declare(strict_types=1);

namespace App\Service\Configurator;

/**
 * Filtres d'attributs de l'API en cours de construction. Plusieurs règles peuvent viser le même attribut : les listes
 * de valeurs admises se croisent et les bornes se resserrent. Une contradiction ne laisse aucun produit possible.
 */
final class AttributeFilters
{
    /** @var array<string, list<string>> attr[code] : la valeur du produit figure dans la liste */
    private array $allowed = [];

    /** @var array<string, list<string>> attrHas[code] : la liste du produit contient chacune de ces valeurs */
    private array $required = [];

    /** @var array<string, float> attrMin[code] */
    private array $min = [];

    /** @var array<string, float> attrMax[code] */
    private array $max = [];

    private bool $impossible = false;

    /**
     * @param list<string> $values
     */
    public function allow(string $code, array $values): void
    {
        $this->allowed[$code] = isset($this->allowed[$code]) ? array_values(array_intersect($this->allowed[$code], $values)) : $values;
        $this->impossible = $this->impossible || [] === $this->allowed[$code];
    }

    public function require(string $code, string $value): void
    {
        $this->required[$code] = array_values(array_unique([...$this->required[$code] ?? [], $value]));
    }

    public function atLeast(string $code, float $number): void
    {
        $this->min[$code] = max($this->min[$code] ?? $number, $number);
    }

    public function atMost(string $code, float $number): void
    {
        $this->max[$code] = min($this->max[$code] ?? $number, $number);
    }

    /** Un produit déjà choisi n'indique pas l'attribut qu'une règle exige : rien ne peut lui correspondre. */
    public function block(): void
    {
        $this->impossible = true;
    }

    /**
     * @return array<string, string>|null paramètres de requête, ou null si aucun produit ne peut convenir
     */
    public function toQuery(): ?array
    {
        foreach ($this->min as $code => $number) {
            $this->impossible = $this->impossible || (isset($this->max[$code]) && $number > $this->max[$code]);
        }
        if ($this->impossible) {
            return null;
        }

        $query = [];
        foreach ($this->allowed as $code => $values) {
            $query["attr[$code]"] = implode(',', $values);
        }
        foreach ($this->required as $code => $values) {
            $query["attrHas[$code]"] = implode(',', $values);
        }
        foreach ($this->min as $code => $number) {
            $query["attrMin[$code]"] = (string) $number;
        }
        foreach ($this->max as $code => $number) {
            $query["attrMax[$code]"] = (string) $number;
        }

        return $query;
    }
}
