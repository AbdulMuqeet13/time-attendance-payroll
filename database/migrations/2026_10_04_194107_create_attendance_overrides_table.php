<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manager decisions applied on top of the computed day, kept apart so rebuilds never lose them:
     * a status override, a waived late, an overtime approval.
     */
    public function up(): void
    {
        Schema::create('attendance_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->nullable();
            $table->boolean('waive_late')->default(false);
            $table->unsignedSmallInteger('approved_overtime_minutes')->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_overrides');
    }
};
