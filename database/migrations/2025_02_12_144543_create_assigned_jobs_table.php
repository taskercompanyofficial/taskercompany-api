<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assigned_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('complaints');
            $table->foreignId('assigned_by')->constrained('staff');
            $table->foreignId('assigned_to')->constrained('staff');
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('status')->default('pending');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->text('customer_remarks')->nullable();
            $table->integer('rating')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assigned_jobs');
    }
};
