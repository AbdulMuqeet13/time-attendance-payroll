<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One employee's pay for a run. Everything needed to explain it is copied in (snapshot), so later
     * changes to the employee, salary or attendance never alter an issued payslip.
     */
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_salary_id')->nullable()->constrained()->nullOnDelete();

            $table->string('employee_code', 30);
            $table->string('employee_name');
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->string('branch')->nullable();
            $table->string('payment_method', 10);
            $table->string('bank_name')->nullable();
            $table->string('account_title')->nullable();
            $table->string('account_number', 50)->nullable();

            $table->decimal('monthly_gross', 18, 2);
            $table->decimal('day_divisor', 6, 2);
            $table->decimal('per_day_rate', 18, 4);
            $table->decimal('per_hour_rate', 18, 4);

            $table->unsignedSmallInteger('period_days');
            $table->unsignedSmallInteger('employed_days');
            $table->unsignedSmallInteger('scheduled_days')->default(0);
            $table->decimal('present_days', 5, 1)->default(0);
            $table->decimal('absent_days', 5, 1)->default(0);
            $table->decimal('half_days', 5, 1)->default(0);
            $table->decimal('paid_leave_days', 5, 1)->default(0);
            $table->decimal('unpaid_leave_days', 5, 1)->default(0);
            $table->unsignedSmallInteger('holidays')->default(0);
            $table->unsignedSmallInteger('weekly_offs')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('short_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('holiday_overtime_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);

            $table->decimal('earnings_total', 18, 2);
            $table->decimal('deductions_total', 18, 2);
            $table->decimal('net_pay', 18, 2);
            $table->decimal('shortfall', 18, 2)->default(0);

            $table->string('payment_status', 20)->default('unpaid');
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
