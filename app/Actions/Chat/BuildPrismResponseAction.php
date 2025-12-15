<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ChatType;
use App\Traits\AsActionTrait;
use Generator;
use Prism\Prism\Facades\Prism;

/**
 * @method static Generator run(int $accountId, array $messages, ChatType $chatType):
 */
class BuildPrismResponseAction
{
    use AsActionTrait;

    public function __invoke(
        int $accountId,
        array $messages,
        ChatType $chatType,
    ): Generator {
        $tools = BuildToolsArrayAction::run($chatType, $accountId);
        $provider = CalculateProviderAction::run($chatType);
        $model = CalculateModelAction::run($chatType);
        $systemPrompt = CalculateSystemPromptAction::run($chatType);
        $providerTools = CalculateProviderToolsAction::run($chatType);

        if (!empty($providerTools)) {
            $tools = []; // Clear tools so can use geminii built-in tools
        }

        return Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt($systemPrompt)
            ->withMessages($messages)
            ->withMaxSteps(5)
            ->withTools($tools)
            ->withProviderTools($providerTools)
            ->asStream();
    }
}
