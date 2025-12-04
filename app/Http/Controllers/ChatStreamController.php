<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ChatMessageRole;
use App\Facades\CurrentAccount;
use App\Http\Requests\ChatStreamRequest;
use App\Managers\Tenant\ChatManager;
use App\Mcp\Prism\Tools\Funnel\CreateFunnelTool;
use App\Mcp\Prism\Tools\Funnel\GetFunnelTool;
use App\Mcp\Prism\Tools\Funnel\ListFunnelsTool;
use App\Mcp\Prism\Tools\Funnel\UpdateFunnelTool;
use App\Mcp\Prism\Tools\Order\ListOrderTool;
use App\Mcp\Prism\Tools\Product\CreateProductTool;
use App\Mcp\Prism\Tools\Product\GetProductTool;
use App\Mcp\Prism\Tools\Product\ListProductsTool;
use App\Mcp\Prism\Tools\Product\UpdateProductTool;
use App\Mcp\Prism\Tools\Template\CreateTemplateTool;
use App\Mcp\Prism\Tools\Template\GetTemplateTool;
use App\Mcp\Prism\Tools\Template\ListTemplatesTool;
use App\Mcp\Prism\Tools\Template\UpdateTemplateTool;
use App\Models\Chat;
use App\Repositories\Tenant\ChatRepository;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\ChunkType;
use Prism\Prism\Prism;
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
        Gate::authorize('viewAny', Chat::class);

        $message = $request->validated('message');
        $chatId = $request->validated('chat_id');

        $accountId = CurrentAccount::get()->id;
        $chat = $chatRepository->findByIdAndUserAndAccount($chatId, $request->user()->id, $accountId);

        // Get count of messages sent today by this user

        $chatManager->createChatMessage(
            chat: $chat,
            role: ChatMessageRole::USER,
            parts: [ChunkType::Text->value => $message],
        );

        $messages = $chatRepository->buildConversationHistory($chat);

        /** @phpstan-ignore-next-line */
        return response()->eventStream(
            function () use ($messages, $chat, $chatManager, $accountId) {
                $parts = [];

                try {
                    $tools = [
                        new GetFunnelTool($accountId),
                        new ListFunnelsTool($accountId),
                        new CreateFunnelTool($accountId),
                        new UpdateFunnelTool($accountId),
                        new GetProductTool($accountId),
                        new ListProductsTool($accountId),
                        new CreateProductTool($accountId),
                        new UpdateProductTool($accountId),
                        new GetTemplateTool($accountId),
                        new ListTemplatesTool($accountId),
                        new CreateTemplateTool($accountId),
                        new UpdateTemplateTool($accountId),
                        new ListOrderTool($accountId),
                    ];
                    $response = Prism::text()
                        ->using('xai', 'grok-code-fast-1')
                        ->withSystemPrompt('You are a helpful assistant for managing e-commerce platform FunnelGen. Funnels are comprised of products and templates. You also have access to orders history. Use the tools to help the user when applicable.')
                        ->withMessages($messages)
                        ->withMaxSteps(5)
                        ->withTools($tools)
                        ->asStream();

                    foreach ($response as $chunk) {
                        $chunkData = [
                            'chunkType' => $chunk->chunkType->value,
                            'content' => $chunk->text,
                        ];

                        if (!empty($chunk->toolName)) {
                            $chunkData['toolName'] = $chunk->toolName;
                        }

                        if (! isset($parts[$chunk->chunkType->value])) {
                            $parts[$chunk->chunkType->value] = '';
                        }

                        $parts[$chunk->chunkType->value] .= $chunk->text;

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

                    yield new StreamedEvent(
                        event: 'update',
                        data: json_encode([
                            'chunkType' => 'error',
                            'content' => 'An error occurred while processing your message.',
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
