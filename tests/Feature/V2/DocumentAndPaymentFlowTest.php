<?php

namespace Tests\Feature\V2;

use App\Models\Contact;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;
use App\Services\Accounting\ApprovalService;
use App\Services\Accounting\DocumentService;
use App\Services\Accounting\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAndPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_posting_and_payment_flow(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'V2 Team',
            'slug' => 'v2-team',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        TeamUser::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamUser::ROLE_OWNER,
        ]);

        $this->assertSame(6, $team->accounts()->count());

        $contact = Contact::create([
            'team_id' => $team->id,
            'type' => 'customer',
            'name' => 'Acme',
        ]);

        $year = now()->year;

        $document = app(DocumentService::class)->create($team, [
            'type' => 'invoice',
            'contact_id' => $contact->id,
            'issue_date' => "{$year}-08-01",
            'lines' => [
                ['description' => 'Service', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 0],
            ],
        ], $user);

        $this->assertSame('200.00', $document->total_amount);
        $this->assertSame('draft', $document->status);
        app(ApprovalService::class)->submitDocument($document, $team, $user);
        $document->refresh();
        $this->assertSame('submitted', $document->status);
        app(ApprovalService::class)->approveDocument($document, $team, $user);
        $document->refresh();
        $this->assertSame('issued', $document->status);
        $this->assertCount(1, $document->lines);
        $this->assertSame(1, $team->journalEntries()->count());

        app(PaymentService::class)->record($team, [
            'document_id' => $document->id,
            'payment_date' => "{$year}-08-02",
            'amount' => 200,
            'method' => 'bank_transfer',
        ], $user);

        $document->refresh();
        $this->assertSame('200.00', $document->paid_amount);
        $this->assertSame('0.00', $document->balance_due);
        $this->assertSame('paid', $document->status);
        $this->assertSame(2, $team->journalEntries()->count());
    }
}
