<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date');
            $table->string('change_type', 20);
            $table->decimal('gross_salary', 18, 2);
            $table->decimal('fixed_deductions', 18, 2)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};
