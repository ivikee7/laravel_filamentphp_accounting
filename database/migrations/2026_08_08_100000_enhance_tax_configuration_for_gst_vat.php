<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rates', function (Blueprint $table): void {
            $table->string('category', 32)->default('standard')->after('code');
            $table->boolean('is_recoverable')->default(true)->after('category');
            $table->index(['team_id', 'category', 'is_active']);
        });

        Schema::table('document_lines', function (Blueprint $table): void {
            $table->boolean('price_includes_tax')->default(false)->after('unit_price');
            $table->string('tax_treatment', 32)->default('taxable')->after('tax_rate_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_lines', function (Blueprint $table): void {
            $table->dropColumn(['price_includes_tax', 'tax_treatment']);
        });

        Schema::table('tax_rates', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'category', 'is_active']);
            $table->dropColumn(['category', 'is_recoverable']);
        });
    }
};
