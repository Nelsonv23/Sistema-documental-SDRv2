<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, ?Model $target = null, array $metadata = [], ?User $actor = null): AuditLog
    {
        if ($actor === null) {
            $authenticated = Auth::user();
            $actor = $authenticated instanceof User ? $authenticated : null;
        }

        $request = app()->bound('request') ? request() : null;

        $actorName = $actor?->name ?? (isset($metadata['actor_name']) ? (string) $metadata['actor_name'] : null);
        $actorEmail = $actor?->email ?? (isset($metadata['email']) ? (string) $metadata['email'] : null);

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actorName,
            'actor_email' => $actorEmail,
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'target_label' => $this->resolveTargetLabel($target),
            'metadata' => $metadata !== [] ? $metadata : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
            'created_at' => now(),
        ]);
    }

    private function resolveTargetLabel(?Model $target): ?string
    {
        if ($target === null) {
            return null;
        }

        foreach (['name', 'email', 'title'] as $attribute) {
            $value = $target->getAttribute($attribute);
            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 255);
            }
        }

        return class_basename($target).' #'.$target->getKey();
    }
}
