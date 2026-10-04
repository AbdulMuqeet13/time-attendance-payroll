<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payroll for one period, for one branch or (branch_id null) the whole company.
     * draft → approved (attendance locked) → paid; a draft can be regenerated or cancelled.
     */
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('earnings_total', 18, 2)->default(0);
            $table->decimal('deductions_total', 18, 2)->default(0);
            $table->decimal('net_total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('settings_snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
