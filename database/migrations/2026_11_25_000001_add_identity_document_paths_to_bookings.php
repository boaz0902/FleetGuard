<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('license_front_path')->nullable();
            $table->string('license_back_path')->nullable();
            $table->string('license_selfie_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'license_front_path',
                'license_back_path',
                'license_selfie_path',
            ]);
        });
    }
};
