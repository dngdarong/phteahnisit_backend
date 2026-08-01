<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * @param string $action e.g. 'user.registered', 'room.approved'
     * @param Model|null $subject the entity the action was performed on
     * @param array<string, mixed> $metadata extra context (old/new values, reason, etc.)
     */
    public function log(?User $actor, string $action, ?Model $subject = null, array $metadata = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
