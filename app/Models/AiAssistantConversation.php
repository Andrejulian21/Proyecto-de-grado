<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiAssistantStatus;
use App\Enums\AiAssistantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAssistantConversation extends Model
{
    protected $table = 'ai_assistant_conversations';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'provider',
        'prompt_version',
        'processing_ms',
        'result_json',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'type' => AiAssistantType::class,
            'status' => AiAssistantStatus::class,
            'result_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiAssistantMessage::class, 'conversation_id');
    }
}
