<?php

namespace Tests\Feature\V2;

use App\Models\Contact;
use App\Models\TaxRate;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;
use App\Services\Accounting\ApprovalService;
use App\Services\Accounting\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaxManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_posts_revenue_and_output_tax_separately(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'Tax Team',
            'slug' => 'tax-team',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        TeamUser::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamUser::ROLE_OWNER,
        ]);

        $taxRate = TaxRate::query()
            ->where('team_id', $team->id)
            ->where('code', 'STD')
            ->firstOrFail();
        $taxRate->update(['rate' => 18]);

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
                [
                    'description' => 'Service',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'tax_rate_id' => $taxRate->id,
                    'tax_treatment' => 'taxable',
                ],
            ],
        ], $user);

        app(ApprovalService::class)->submitDocument($document, $team, $user);
        app(ApprovalService::class)->approveDocument($document->refresh(), $team, $user);
        $document->refresh();

        $this->assertSame('100.00', $document->subtotal_amount);
        $this->assertSame('18.00', $document->tax_amount);
        $this->assertSame('118.00', $document->total_amount);

        $entryId = $team->journalEntries()
            ->where('source_type', 'document')
            ->where('source_id', $document->id)
            ->value('id');

        $lines = DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.journal_entry_id', $entryId)
            ->select('accounts.code', 'journal_lines.debit', 'journal_lines.credit')
            ->get()
            ->keyBy('code');

        $this->assertEquals(118.0, (float) $lines['1100']->debit);
        $this->assertEquals(100.0, (float) $lines['4000']->credit);
        $this->assertEquals(18.0, (float) $lines['2200']->credit);
    }

    public function test_inclusive_pricing_calculates_gst_vat_correctly(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'Inclusive Tax Team',
            'slug' => 'inclusive-tax-team',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        TeamUser::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamUser::ROLE_OWNER,
        ]);

        $team->taxProfile()->update([
            'prices_include_tax' => true,
            'tax_precision' => 2,
            'rounding_mode' => 'half_up',
            'country_code' => 'IN',
            'currency_code' => 'INR',
        ]);

        $taxRate = TaxRate::query()
            ->where('team_id', $team->id)
            ->where('code', 'STD')
            ->firstOrFail();
        $taxRate->update(['rate' => 18]);

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
            'currency_code' => 'INR',
            'lines' => [
                [
                    'description' => 'Inclusive line',
                    'quantity' => 1,
                    'unit_price' => 118,
                    'tax_rate_id' => $taxRate->id,
                ],
            ],
        ], $user);

        $this->assertSame('100.00', $document->subtotal_amount);
        $this->assertSame('18.00', $document->tax_amount);
        $this->assertSame('118.00', $document->total_amount);
        $this->assertTrue((bool) $document->lines()->firstOrFail()->price_includes_tax);
    }

    public function test_bill_posts_input_tax_recoverable_separately(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'Vendor Tax Team',
            'slug' => 'vendor-tax-team',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        TeamUser::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamUser::ROLE_OWNER,
        ]);

        $taxRate = TaxRate::query()
            ->where('team_id', $team->id)
            ->where('code', 'STD')
            ->firstOrFail();
        $taxRate->update(['rate' => 18]);

        $contact = Contact::create([
            'team_id' => $team->id,
            'type' => 'vendor',
            'name' => 'Vendor One',
        ]);

        $year = now()->year;
        $document = app(DocumentService::class)->create($team, [
            'type' => 'bill',
            'contact_id' => $contact->id,
            'issue_date' => "{$year}-08-01",
            'lines' => [
                [
                    'description' => 'Purchase',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'tax_rate_id' => $taxRate->id,
                    'tax_treatment' => 'taxable',
                ],
            ],
        ], $user);

        app(ApprovalService::class)->submitDocument($document, $team, $user);
        app(ApprovalService::class)->approveDocument($document->refresh(), $team, $user);
        $document->refresh();

        $entryId = $team->journalEntries()
            ->where('source_type', 'document')
            ->where('source_id', $document->id)
            ->value('id');

        $lines = DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.journal_entry_id', $entryId)
            ->select('accounts.code', 'journal_lines.debit', 'journal_lines.credit')
            ->get()
            ->keyBy('code');

        $this->assertEquals(100.0, (float) $lines['5000']->debit);
        $this->assertEquals(18.0, (float) $lines['1300']->debit);
        $this->assertEquals(118.0, (float) $lines['2100']->credit);
    }

    public function test_intra_state_gst_splits_into_cgst_and_sgst_components(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'GST Intra Team',
            'slug' => 'gst-intra-team',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        TeamUser::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamUser::ROLE_OWNER,
        ]);

        $year = now()->year;
        $gstIntra = TaxRate::query()->create([
            'team_id' => $team->id,
            'name' => 'GST 18 Intra',
            'code' => 'GST18-INTRA',
            'tax_type' => 'gst',
            'applies_to_scope' => 'intra_state',
            'category' => 'standard',
            'is_recoverable' => true,
            'rate' => 18,
            'components' => [
                ['tax_code' => 'CGST', 'tax_name' => 'Central GST', 'tax_rate' => 9, 'tax_scope' => 'intra_state'],
                ['tax_code' => 'SGST', 'tax_name' => 'State GST', 'tax_rate' => 9, 'tax_scope' => 'intra_state'],
            ],
            'effective_from' => "{$year}-01-01",
            'is_active' => true,
        ]);

        $contact = Contact::create([
            'team_id' => $team->id,
            'type' => 'customer',
            'name' => 'Acme',
        ]);

        $document = app(DocumentService::class)->create($team, [
            'type' => 'invoice',
            'supply_scope' => 'intra_state',
            'contact_id' => $contact->id,
            'issue_date' => "{$year}-08-01",
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate_id' => $gstIntra->id],
            ],
        ], $user);

        $lineBreakdown = $document->lines()->firstOrFail()->tax_breakdown;
        $this->assertCount(2, $lineBreakdown);
        $this->assertSame('CGST', $lineBreakdown[0]['tax_code']);
        $this->assertSame('SGST', $lineBreakdown[1]['tax_code']);
        $this->assertSame(9.0, (float) $lineBreakdown[0]['tax_amount']);
        $this->assertSame(9.0, (float) $lineBreakdown[1]['tax_amount']);
        $this->assertSame('18.00', $document->tax_amount);
    }

    public function test_inter_state_gst_uses_igst_component(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'GST Inter Team',
            'slug' => 'gst-inter-team',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        TeamUser::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamUser::ROLE_OWNER,
        ]);

        $year = now()->year;
        $igst = TaxRate::query()->create([
            'team_id' => $team->id,
            'name' => 'IGST 18',
            'code' => 'IGST18',
            'tax_type' => 'gst',
            'applies_to_scope' => 'inter_state',
            'category' => 'standard',
            'is_recoverable' => true,
            'rate' => 18,
            'components' => [
                ['tax_code' => 'IGST', 'tax_name' => 'Integrated GST', 'tax_rate' => 18, 'tax_scope' => 'inter_state'],
            ],
            'effective_from' => "{$year}-01-01",
            'is_active' => true,
        ]);

        $contact = Contact::create([
            'team_id' => $team->id,
            'type' => 'customer',
            'name' => 'Acme',
        ]);

        $document = app(DocumentService::class)->create($team, [
            'type' => 'invoice',
            'supply_scope' => 'inter_state',
            'contact_id' => $contact->id,
            'issue_date' => "{$year}-08-01",
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate_id' => $igst->id],
            ],
        ], $user);

        $lineBreakdown = $document->lines()->firstOrFail()->tax_breakdown;
        $this->assertCount(1, $lineBreakdown);
        $this->assertSame('IGST', $lineBreakdown[0]['tax_code']);
        $this->assertSame(18.0, (float) $lineBreakdown[0]['tax_amount']);
        $this->assertSame('18.00', $document->tax_amount);
    }

    public function test_vat_tax_type_is_supported_for_future_tax_systems(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'VAT Team',
            'slug' => 'vat-team',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        TeamUser::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamUser::ROLE_OWNER,
        ]);

        $year = now()->year;
        $vat = TaxRate::query()->create([
            'team_id' => $team->id,
            'name' => 'VAT 20',
            'code' => 'VAT20',
            'tax_type' => 'vat',
            'applies_to_scope' => 'domestic',
            'category' => 'standard',
            'is_recoverable' => true,
            'rate' => 20,
            'components' => null,
            'effective_from' => "{$year}-01-01",
            'is_active' => true,
        ]);

        $contact = Contact::create([
            'team_id' => $team->id,
            'type' => 'customer',
            'name' => 'Client',
        ]);

        $document = app(DocumentService::class)->create($team, [
            'type' => 'invoice',
            'supply_scope' => 'domestic',
            'contact_id' => $contact->id,
            'issue_date' => "{$year}-08-01",
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate_id' => $vat->id],
            ],
        ], $user);

        $lineBreakdown = $document->lines()->firstOrFail()->tax_breakdown;
        $this->assertCount(1, $lineBreakdown);
        $this->assertSame('vat', $lineBreakdown[0]['tax_type']);
        $this->assertSame('20.00', $document->tax_amount);
    }
}
