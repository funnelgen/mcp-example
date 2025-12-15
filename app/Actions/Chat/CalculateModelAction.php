<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ChatType;
use App\Traits\AsActionTrait;

/**
 * @method static string run(ChatType $type):
 */
class CalculateModelAction
{
    use AsActionTrait;
    private const string GROK_CODE_FAST_1 = 'grok-code-fast-1';
    private const string GEMINI_3_PRO_PREVIEW = 'gemini-3-pro-preview';

    public function __invoke(ChatType $type): string
    {
        return match ($type) {
            ChatType::SEARCH => self::GEMINI_3_PRO_PREVIEW,
            default => self::GROK_CODE_FAST_1,
        };
    }
}
