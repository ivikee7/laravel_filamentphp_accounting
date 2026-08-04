<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('country_code', 2)->default('US');
            $table->string('currency_code', 3)->default('USD');
            $table->unsignedTinyInteger('tax_precision')->default(2);
            $table->string('rounding_mode')->default('half_up');
            $table->boolean('prices_include_tax')->default(false);
            $table->timestamps();

            $table->unique('team_id');
        });

        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->decimal('rate', 8, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['team_id', 'code', 'effective_from']);
            $table->index(['team_id', 'is_active', 'effective_from']);
        });

        Schema::create('fiscal_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'start_date', 'end_date']);
            $table->index(['team_id', 'is_closed']);
        });

        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->foreignId('fiscal_period_id')->nullable()->after('team_id')->constrained('fiscal_periods')->nullOnDelete();
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('tax_profile_id')->nullable()->after('team_id')->constrained('tax_profiles')->nullOnDelete();
            $table->json('tax_breakdown')->nullable()->after('tax_amount');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->foreignId('approved_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->index(['team_id', 'status', 'submitted_at']);
        });

        Schema::table('document_lines', function (Blueprint $table): void {
            $table->foreignId('tax_rate_id')->nullable()->after('tax_rate')->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 18, 2)->default(0)->after('tax_rate');
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->string('status')->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status', 'submitted_at']);
            $table->index(['approvable_type', 'approvable_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('payload');
            $table->string('previous_checksum', 64)->nullable();
            $table->string('checksum', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['team_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->unique('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('approval_requests');

        Schema::table('document_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropColumn('tax_amount');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'status', 'submitted_at']);
            $table->dropConstrainedForeignId('tax_profile_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['tax_breakdown', 'submitted_at', 'approved_at', 'rejected_at']);
        });

        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fiscal_period_id');
        });

        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_profiles');
    }
};
