<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatType: int
{
    case CHAT = 1;
    case DESIGN = 2;
    case SEARCH = 3;

    public function label(): string
    {
        return match ($this) {
            self::CHAT => 'chat',
            self::DESIGN => 'design',
            self::SEARCH => 'search',
        };
    }
}
