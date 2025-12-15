<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ChatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChatStreamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string|max:10000',
            'chat_id' => 'required|string|exists:chats,id',
            'type' => ['required', Rule::enum(ChatType::class)],
        ];
    }
}
