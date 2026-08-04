<?php

namespace App\Services\Accounting;

use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly AuditLogService $audit,
    ) {
    }

    public function submitDocument(Document $document, Team $team, User $actor): ApprovalRequest
    {
        if ((int) $document->team_id !== (int) $team->getKey()) {
            throw ValidationException::withMessages(['document' => 'Document does not belong to tenant.']);
        }

        if ($document->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft documents can be submitted.']);
        }

        return DB::transaction(function () use ($document, $team, $actor): ApprovalRequest {
            $document->update([
                'status' => 'submitted',
                'submitted_at' => Carbon::now(),
            ]);

            $approval = ApprovalRequest::query()->updateOrCreate(
                [
                    'team_id' => $team->getKey(),
                    'approvable_type' => Document::class,
                    'approvable_id' => $document->getKey(),
                ],
                [
                    'status' => 'pending',
                    'submitted_by' => $actor->getKey(),
                    'submitted_at' => Carbon::now(),
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'remarks' => null,
                ],
            );

            $this->audit->append($team, $actor, 'document.submitted', Document::class, (int) $document->getKey(), [
                'number' => $document->number,
                'type' => $document->type,
            ]);

            return $approval;
        });
    }

    public function approveDocument(Document $document, Team $team, User $actor, ?string $remarks = null): Document
    {
        if (! $actor->canApproveTransactions($team)) {
            throw ValidationException::withMessages(['approval' => 'User does not have approval permissions.']);
        }

        if ($document->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'Only submitted documents can be approved.']);
        }

        return DB::transaction(function () use ($document, $team, $actor, $remarks): Document {
            ApprovalRequest::query()
                ->where('team_id', $team->getKey())
                ->where('approvable_type', Document::class)
                ->where('approvable_id', $document->getKey())
                ->update([
                    'status' => 'approved',
                    'approved_by' => $actor->getKey(),
                    'approved_at' => Carbon::now(),
                    'remarks' => $remarks,
                ]);

            $document->update([
                'status' => 'issued',
                'approved_at' => Carbon::now(),
                'approved_by' => $actor->getKey(),
                'rejected_at' => null,
                'rejected_by' => null,
            ]);

            $this->documents->postIssuedDocument($document, $team, $actor);
            $this->documents->recalculate($document);

            $this->audit->append($team, $actor, 'document.approved', Document::class, (int) $document->getKey(), [
                'remarks' => $remarks,
            ]);

            return $document->refresh();
        });
    }

    public function rejectDocument(Document $document, Team $team, User $actor, ?string $remarks = null): Document
    {
        if (! $actor->canApproveTransactions($team)) {
            throw ValidationException::withMessages(['approval' => 'User does not have approval permissions.']);
        }

        if ($document->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'Only submitted documents can be rejected.']);
        }

        return DB::transaction(function () use ($document, $team, $actor, $remarks): Document {
            ApprovalRequest::query()
                ->where('team_id', $team->getKey())
                ->where('approvable_type', Document::class)
                ->where('approvable_id', $document->getKey())
                ->update([
                    'status' => 'rejected',
                    'rejected_by' => $actor->getKey(),
                    'rejected_at' => Carbon::now(),
                    'remarks' => $remarks,
                ]);

            $document->update([
                'status' => 'rejected',
                'rejected_at' => Carbon::now(),
                'rejected_by' => $actor->getKey(),
            ]);

            $this->audit->append($team, $actor, 'document.rejected', Document::class, (int) $document->getKey(), [
                'remarks' => $remarks,
            ]);

            return $document->refresh();
        });
    }
}
