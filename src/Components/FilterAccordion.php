<?php

namespace App\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('filter_accordion')]
final class FilterAccordion
{
    public string $title = '';
    public bool $open = false;
    public ?string $badge = null;
}
