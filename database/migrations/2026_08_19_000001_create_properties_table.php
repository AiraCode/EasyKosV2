<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // owner
            $table->string('name');
            $table->string('type'); // 'Standard Single', 'Deluxe AC', 'Premium Suite', 'Double Bed'
            $table->string('category')->default('single'); // 'single', 'deluxe', 'premium', 'double'
            $table->string('address');
            $table->integer('price');
            $table->decimal('rating', 3, 1)->default(4.8);
            $table->integer('reviews_count')->default(10);
            $table->string('image');
            $table->json('gallery')->nullable();
            $table->json('facilities')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('Available'); // 'Available', 'Full'
            $table->integer('total_rooms')->default(5);
            $table->integer('occupied_rooms')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
