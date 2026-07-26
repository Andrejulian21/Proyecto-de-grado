<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiMessageRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAssistantMessage extends Model
{
    protected $table = 'ai_assistant_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'structured_json',
    ];

    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'structured_json' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiAssistantConversation::class, 'conversation_id');
    }
}
