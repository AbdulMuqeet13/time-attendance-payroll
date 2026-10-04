<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each earning and deduction line of a payslip, as quantity × rate = amount, so the payslip explains itself.
     */
    public function up(): void
    {
        Schema::create('payslip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->string('side', 10);
            $table->string('category', 30);
            $table->string('name');
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit', 10)->nullable();
            $table->decimal('rate', 18, 4)->nullable();
            $table->decimal('amount', 18, 2);
            $table->nullableMorphs('source');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_items');
    }
};
