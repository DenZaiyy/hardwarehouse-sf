<?php

namespace App\Enum;

enum AttributeType: string
{
    case TEXT = 'TEXT';
    case NUMBER = 'NUMBER';
    case BOOLEAN = 'BOOLEAN';
    case SELECT = 'SELECT';
}
