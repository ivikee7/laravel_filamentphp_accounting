<?php

namespace Tests\Feature\V2;

use App\Models\Contact;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;
use App\Services\Accounting\ApprovalService;
use App\Services\Accounting\DocumentService;
use App\Services\Accounting\PaymentService;
use App\Services\Accounting\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_use_v2_ledger(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'Reports Team',
            'slug' => 'reports-team-v2',
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

        $invoice = app(DocumentService::class)->create($team, [
            'type' => 'invoice',
            'contact_id' => $contact->id,
            'issue_date' => "{$year}-08-01",
            'lines' => [
                ['description' => 'Package', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0],
            ],
        ], $user);
        app(ApprovalService::class)->submitDocument($invoice, $team, $user);
        app(ApprovalService::class)->approveDocument($invoice->refresh(), $team, $user);
        $invoice->refresh();

        app(PaymentService::class)->record($team, [
            'document_id' => $invoice->id,
            'payment_date' => "{$year}-08-02",
            'amount' => 300,
            'method' => 'cash',
        ], $user);

        $reports = app(ReportService::class);

        $trial = $reports->trialBalance($team);
        $this->assertNotEmpty($trial['lines']);

        $pnl = $reports->profitAndLoss($team);
        $this->assertSame(500.0, $pnl['totals']['income']);
        $this->assertSame(500.0, $pnl['totals']['net']);

        $cashFlow = $reports->cashFlow($team);
        $this->assertSame(300.0, $cashFlow['totals']['inflows']);
        $this->assertSame(0.0, $cashFlow['totals']['outflows']);
        $this->assertSame(300.0, $cashFlow['totals']['net']);
    }
}
