<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Chat\BuildPrismResponseAction;
use App\Enums\ChatMessageRole;
use App\Enums\ChatType;
use App\Facades\CurrentAccount;
use App\Http\Requests\ChatStreamRequest;
use App\Managers\Tenant\ChatManager;
use App\Models\Chat;
use App\Repositories\Tenant\ChatRepository;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ChatStreamController handles streaming chat responses from third-party AI services.
 *
 * This controller provides a streaming endpoint for real-time chat responses.
 * Frontend clients can GET chat messages and receive streaming responses.
 *
 * Usage:
 * GET /chat/stream?message=Hello&context[user_id]=123&chat_id=123
 *
 * The response is a streamed text response with chunks sent progressively.
 */
class ChatStreamController extends Controller
{
    public function stream(
        ChatStreamRequest $request,
        ChatManager $chatManager,
        ChatRepository $chatRepository,
    ): StreamedResponse {
        set_time_limit(0);

        Gate::authorize('viewAny', Chat::class);

        $message = $request->validated('message');
        $chatId = $request->validated('chat_id');
        $type = ChatType::from((int) $request->validated('type'));
        $accountId = CurrentAccount::get()->id;

        $chat = $chatRepository->findByIdAndUserAndAccount($chatId, $request->user()->id, $accountId);

        $chatManager->createChatMessage(
            chat: $chat,
            role: ChatMessageRole::USER,
            parts: ['text' => $message],
        );

        $messages = $chatRepository->buildConversationHistory($chat);

        /** @phpstan-ignore-next-line */
        return response()->eventStream(
            function () use ($messages, $chat, $chatManager, $accountId, $type) {
                $parts = [];

                try {
                    $response = BuildPrismResponseAction::run(
                        accountId: $accountId,
                        messages: $messages,
                        chatType: $type,
                    );

                    foreach ($response as $event) {
                        $chunkData = match (true) {
                            $event instanceof TextDeltaEvent => [
                                'chunkType' => 'text',
                                'content' => $event->delta,
                            ],
                            $event instanceof ToolCallEvent => [
                                'chunkType' => 'tool_call',
                                'content' => '',
                                'toolName' => $event->toolCall->name,
                            ],
                            $event instanceof ThinkingEvent => [
                                'chunkType' => 'thinking',
                                'content' => $event->delta,
                            ],
                            default => null,
                        };

                        if ($chunkData === null) {
                            continue;
                        }

                        $chunkType = $chunkData['chunkType'];
                        $content = $chunkData['content'];

                        if (! isset($parts[$chunkType])) {
                            $parts[$chunkType] = '';
                        }

                        $parts[$chunkType] .= $content;

                        yield new StreamedEvent(
                            event: 'update',
                            data: json_encode($chunkData)
                        );
                    }

                    if ($parts !== []) {
                        $chatManager->createChatMessage(
                            chat: $chat,
                            role: ChatMessageRole::ASSISTANT,
                            parts: $parts,
                        );
                        $chat->touch();
                    }
                }
                catch (\Throwable $e) {
                    Log::error('Chat stream error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'chat_id' => $chat->id,
                    ]);

                    $errorMessage = 'An error occurred while processing your message. Please try again later.';

                    if ($e instanceof PrismProviderOverloadedException) {
                        $errorMessage = $e->getMessage();
                    }

                    $errorMessage = $e->getMessage();

                    yield new StreamedEvent(
                        event: 'update',
                        data: json_encode([
                            'chunkType' => 'error',
                            'content' => $errorMessage,
                        ])
                    );
                }
            },
            headers: [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, private',
            'Connection' => 'keep-alive',
        ],
            endStreamWith: new StreamedEvent(event: 'update', data: '</stream>')
        );
    }
}
