<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rates', function (Blueprint $table): void {
            $table->string('tax_type', 32)->default('gst')->after('code');
            $table->string('applies_to_scope', 32)->default('all')->after('tax_type');
            $table->json('components')->nullable()->after('rate');
            $table->index(['team_id', 'tax_type', 'applies_to_scope', 'is_active'], 'tax_rates_team_type_scope_active_idx');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->string('supply_scope', 32)->default('all')->after('currency_code');
            $table->index(['team_id', 'supply_scope'], 'documents_team_supply_scope_idx');
        });

        Schema::table('document_lines', function (Blueprint $table): void {
            $table->json('tax_breakdown')->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('document_lines', function (Blueprint $table): void {
            $table->dropColumn('tax_breakdown');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_team_supply_scope_idx');
            $table->dropColumn('supply_scope');
        });

        Schema::table('tax_rates', function (Blueprint $table): void {
            $table->dropIndex('tax_rates_team_type_scope_active_idx');
            $table->dropColumn(['tax_type', 'applies_to_scope', 'components']);
        });
    }
};
