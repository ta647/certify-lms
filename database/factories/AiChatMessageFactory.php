<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $conversation = AiChatConversation::factory()->create();

        return [
            'ai_chat_conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
            'role' => AiChatMessageRole::User->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => fake()->sentence(10),
            'error_detail' => null,
            'model' => null,
            'response_time_ms' => null,
            'output_tokens' => null,
        ];
    }

    public function forConversation(AiChatConversation $conversation): static
    {
        return $this->state(fn () => [
            'ai_chat_conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
        ]);
    }

    public function assistant(): static
    {
        return $this->state(fn () => ['role' => AiChatMessageRole::Assistant->value]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => AiChatMessageStatus::Pending->value, 'content' => '']);
    }

    public function error(): static
    {
        return $this->state(fn () => [
            'status' => AiChatMessageStatus::Error->value,
            'content' => '',
            'error_detail' => 'upstream 502',
        ]);
    }
}
