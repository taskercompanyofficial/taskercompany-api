<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('username')->unique();
            $table->string('father_name');
            $table->string('contact_email')->nullable();
            $table->string('phone_number');
            $table->string('secondary_phone_number')->nullable();
            $table->string('password');
            $table->text('full_address');
            $table->string('state');
            $table->string('city');
            $table->decimal('salary', 10, 2);
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('cnic_front')->nullable()->nullable();
            $table->string('cnic_back')->nullable()->nullable();
            $table->string('account_maintanance_certificate')->nullable()->nullable();
            $table->string('blank_check')->nullable()->nullable();
            $table->string('reference_1_name')->nullable();
            $table->string('reference_1_number')->nullable();
            $table->string('reference_1_cnic')->nullable();
            $table->string('reference_2_name')->nullable();
            $table->string('reference_2_number')->nullable();
            $table->string('reference_2_cnic')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('role');
            $table->string('status')->default('active');
            $table->string('has_crm_access')->default('no');
            $table->string('notification')->default('both');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
