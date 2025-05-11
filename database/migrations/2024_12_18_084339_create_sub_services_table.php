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
        Schema::create('sub_services', function (Blueprint $table) {
            $table->id();
            $table->uuid('unique_id')->unique()->index();
            $table->string('category_id')->index();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade')->index();
            $table->string('name')->index();
            $table->string('slug')->unique()->index();
            $table->text('description');
            $table->text('keywords')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2)->index();
            $table->decimal('discount', 5, 2)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_services');
    }
};
