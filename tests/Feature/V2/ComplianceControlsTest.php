<?php

namespace Tests\Feature\V2;

use App\Models\Contact;
use App\Models\FiscalPeriod;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;
use App\Services\Accounting\ApprovalService;
use App\Services\Accounting\DocumentService;
use App\Services\Accounting\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComplianceControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_period_blocks_transactions(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'Compliance Team',
            'slug' => 'compliance-team',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        TeamUser::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamUser::ROLE_OWNER,
        ]);

        $year = now()->year;
        FiscalPeriod::query()
            ->where('team_id', $team->id)
            ->where('name', (string) $year)
            ->update(['is_closed' => true]);

        $contact = Contact::create([
            'team_id' => $team->id,
            'type' => 'customer',
            'name' => 'Acme',
        ]);

        $this->expectException(ValidationException::class);
        app(DocumentService::class)->create($team, [
            'type' => 'invoice',
            'contact_id' => $contact->id,
            'issue_date' => "{$year}-08-01",
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
            ],
        ], $user);
    }

    public function test_approval_and_audit_logs_are_created(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'Audit Team',
            'slug' => 'audit-team',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        TeamUser::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamUser::ROLE_OWNER,
        ]);

        $contact = Contact::create([
            'team_id' => $team->id,
            'type' => 'customer',
            'name' => 'Client',
        ]);

        $year = now()->year;
        $document = app(DocumentService::class)->create($team, [
            'type' => 'invoice',
            'contact_id' => $contact->id,
            'issue_date' => "{$year}-08-01",
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 5],
            ],
        ], $user);

        app(ApprovalService::class)->submitDocument($document, $team, $user);
        app(ApprovalService::class)->approveDocument($document->refresh(), $team, $user);

        app(PaymentService::class)->record($team, [
            'document_id' => $document->id,
            'payment_date' => "{$year}-08-02",
            'amount' => 105,
            'method' => 'cash',
        ], $user);

        $this->assertGreaterThanOrEqual(5, $team->auditLogs()->count());
        $checksums = $team->auditLogs()->orderBy('id')->pluck('checksum')->all();
        $this->assertCount(count(array_unique($checksums)), $checksums);
    }
}
