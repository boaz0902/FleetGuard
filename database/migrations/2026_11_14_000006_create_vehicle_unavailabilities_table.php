<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_id');
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->string('reason');
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();
            $table->index(['vehicle_id', 'start_datetime', 'end_datetime'], 'vehicle_unavailability_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_unavailabilities');
    }
};
