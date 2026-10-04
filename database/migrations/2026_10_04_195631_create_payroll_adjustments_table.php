<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-off additions (bonus, allowance, commission, arrears) and deductions (fine, other) for a month.
     * payroll_run_id is set once an approved run has paid it.
     */
    public function up(): void
    {
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->string('kind', 20);
            $table->string('name');
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
            $table->uuid('batch_id')->nullable()->index();
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
