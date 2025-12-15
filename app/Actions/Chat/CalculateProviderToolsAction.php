<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ChatType;
use App\Traits\AsActionTrait;
use Prism\Prism\ValueObjects\ProviderTool;

/**
 * @method static array run(ChatType $type):
 */
class CalculateProviderToolsAction
{
    use AsActionTrait;

    public function __invoke(ChatType $type): array
    {
        return $type === ChatType::SEARCH
            ? [new ProviderTool('google_search')]
            : [];
    }
}
