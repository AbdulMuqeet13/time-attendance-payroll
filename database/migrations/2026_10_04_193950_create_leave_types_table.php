<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique();
            $table->boolean('is_paid')->default(true);
            $table->decimal('yearly_quota', 5, 1)->default(0);
            $table->decimal('carry_forward_max', 5, 1)->default(0);
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->string('gender', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();

        DB::table('leave_types')->insert([
            ['name' => 'Annual Leave', 'code' => 'AL', 'is_paid' => true, 'yearly_quota' => 14, 'carry_forward_max' => 7, 'allow_half_day' => true, 'requires_attachment' => false, 'gender' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sick Leave', 'code' => 'SL', 'is_paid' => true, 'yearly_quota' => 8, 'carry_forward_max' => 0, 'allow_half_day' => true, 'requires_attachment' => false, 'gender' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Casual Leave', 'code' => 'CL', 'is_paid' => true, 'yearly_quota' => 10, 'carry_forward_max' => 0, 'allow_half_day' => true, 'requires_attachment' => false, 'gender' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Unpaid Leave', 'code' => 'UL', 'is_paid' => false, 'yearly_quota' => 0, 'carry_forward_max' => 0, 'allow_half_day' => true, 'requires_attachment' => false, 'gender' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
