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
        Schema::create('staff_attendences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff');
            $table->string('check_in')->nullable();
            $table->string('check_in_location')->nullable();
            $table->string('check_in_longitude')->nullable();
            $table->string('check_in_latitude')->nullable();
            $table->string('check_in_time')->nullable();
            $table->string('check_out')->nullable();
            $table->string('check_out_location')->nullable();
            $table->string('check_out_longitude')->nullable();
            $table->string('check_out_latitude')->nullable();
            $table->string('check_out_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_attendences');
    }
};
