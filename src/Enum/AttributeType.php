<?php

namespace App\Enum;

enum AttributeType: string
{
    case TEXT = 'TEXT';
    case NUMBER = 'NUMBER';
    case BOOLEAN = 'BOOLEAN';
    case SELECT = 'SELECT';
    // Plusieurs valeurs par produit (formats acceptés par un boîtier, sockets d'un refroidissement…)
    case MULTI_SELECT = 'MULTI_SELECT';
}
