<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paid public holidays. branch_id null = every branch.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['date', 'branch_id']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
