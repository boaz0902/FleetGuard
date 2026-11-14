<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('vehicle_id');
            $table->string('pickup_location');
            $table->dateTime('pickup_datetime');
            $table->string('return_location');
            $table->dateTime('return_datetime');
            $table->unsignedInteger('rental_duration_days');
            $table->decimal('daily_rate_snapshot', 10, 2);
            $table->decimal('estimated_total', 12, 2);
            $table->text('customer_notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'ongoing', 'completed', 'cancelled'])->default('pending');
            $table->text('admin_remarks')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();
            $table->index(['vehicle_id', 'status']);
            $table->index(['pickup_datetime', 'return_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
