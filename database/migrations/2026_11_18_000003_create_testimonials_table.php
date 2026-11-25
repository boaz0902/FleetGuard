<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('customer_display_name', 120);
            $table->text('review_content');
            $table->unsignedTinyInteger('rating');
            $table->date('review_date');
            $table->string('profile_image_path')->nullable();
            $table->string('source', 32);
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('source');
            $table->index('is_featured');
            $table->index('display_order');
            $table->index('review_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
