<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every scan ever received, plus manual punches. Rows are never edited: a wrong punch is voided.
     * Punches whose PIN matched nobody keep employee_id null and are linked once an employee gets that PIN.
     * dedupe_hash makes device re-pushes, backup restores and log imports idempotent.
     */
    public function up(): void
    {
        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pin', 20)->nullable();
            $table->dateTime('punched_at');
            $table->unsignedTinyInteger('punch_state')->nullable();
            $table->unsignedSmallInteger('verify_type')->nullable();
            $table->string('work_code', 20)->nullable();
            $table->string('source', 20);
            $table->char('dedupe_hash', 40)->unique();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'punched_at']);
            $table->index(['pin', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
    }
};
