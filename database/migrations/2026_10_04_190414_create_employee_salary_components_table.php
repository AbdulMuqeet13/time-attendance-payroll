<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_salary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['employee_salary_id', 'salary_component_id'], 'salary_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
