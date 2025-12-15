<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatMessageRole: int
{
    case USER = 1;
    case ASSISTANT = 2;
    case SYSTEM = 3;

    public function label(): string
    {
        return match ($this) {
            self::USER => 'User',
            self::ASSISTANT => 'Assistant',
            self::SYSTEM => 'System',
        };
    }
}
