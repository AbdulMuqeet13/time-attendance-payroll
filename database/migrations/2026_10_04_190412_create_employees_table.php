<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 30)->unique();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('cnic', 20)->nullable()->unique();
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();

            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reports_to_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->string('employment_type', 20);
            $table->string('status', 20)->default('active');
            $table->date('joining_date');
            $table->date('confirmation_date')->nullable();
            $table->date('exit_date')->nullable();

            $table->string('payment_method', 10)->default('bank');
            $table->string('bank_name')->nullable();
            $table->string('account_title')->nullable();
            $table->string('account_number', 50)->nullable();

            /** The user ID on every ZKTeco device. One PIN per employee, shared by all devices. */
            $table->string('device_pin', 14)->nullable()->unique();

            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
