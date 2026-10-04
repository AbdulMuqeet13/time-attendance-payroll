<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Installments taken from payslips (or repaid by hand when payslip_id is null).
     */
    public function up(): void
    {
        Schema::create('advance_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_advance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('recovered_on');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_recoveries');
    }
};
