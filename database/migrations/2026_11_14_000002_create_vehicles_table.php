<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('brand');
            $table->string('model');
            $table->string('category');
            $table->text('description')->nullable();
            $table->decimal('daily_rate', 10, 2);
            $table->unsignedTinyInteger('passenger_capacity');
            $table->string('transmission');
            $table->string('fuel_type');
            $table->string('image_path')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamps();

            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
