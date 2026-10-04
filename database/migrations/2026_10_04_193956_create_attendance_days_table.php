<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The processed result per employee, date and shift occurrence (shift_id null = no shift that day).
     * Rows are rebuilt from punches, the roster, holidays, leaves and overrides; never edited directly.
     * locked_at is set when a payroll run using the day is approved.
     */
    public function up(): void
    {
        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('day_type', 20);
            $table->string('status', 20);
            $table->dateTime('scheduled_start')->nullable();
            $table->dateTime('scheduled_end')->nullable();
            $table->unsignedSmallInteger('scheduled_minutes')->default(0);
            $table->dateTime('first_in')->nullable();
            $table->dateTime('last_out')->nullable();
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->unsignedSmallInteger('approved_overtime_minutes')->nullable();
            $table->boolean('is_missing_checkout')->default(false);
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('leave_fraction', 2, 1)->default(0);
            $table->boolean('leave_is_paid')->default(false);
            $table->boolean('is_overridden')->default(false);
            $table->string('note')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_days');
    }
};
