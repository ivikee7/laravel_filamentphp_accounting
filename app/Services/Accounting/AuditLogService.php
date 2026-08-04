<?php

namespace App\Services\Accounting;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function append(Team $team, ?User $actor, string $eventType, string $entityType, int $entityId, array $payload = []): AuditLog
    {
        $timestamp = Carbon::now();
        $previousChecksum = AuditLog::query()
            ->where('team_id', $team->getKey())
            ->latest('id')
            ->value('checksum');

        $checksum = hash('sha256', json_encode([
            'team_id' => $team->getKey(),
            'actor_id' => $actor?->getKey(),
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'previous_checksum' => $previousChecksum,
            'created_at' => $timestamp->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        return AuditLog::query()->create([
            'team_id' => $team->getKey(),
            'actor_id' => $actor?->getKey(),
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'previous_checksum' => $previousChecksum,
            'checksum' => $checksum,
            'created_at' => $timestamp,
        ]);
    }
}
