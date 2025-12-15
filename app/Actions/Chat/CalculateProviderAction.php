<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ChatType;
use App\Traits\AsActionTrait;
use Prism\Prism\Enums\Provider;

/**
 * @method static string run(ChatType $type):
 */
class CalculateProviderAction
{
    use AsActionTrait;

    public function __invoke(ChatType $type): Provider
    {
        return match ($type) {
            ChatType::SEARCH => Provider::Gemini,
            default => Provider::XAI,
        };
    }
}
