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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // tenant
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->integer('duration_months')->default(1);
            $table->integer('total_price');
            $table->integer('discount_amount')->default(0);
            $table->string('payment_method')->default('ovo');
            $table->string('voucher_code')->nullable();
            $table->string('status')->default('Active'); // 'Active', 'Completed', 'Cancelled'
            $table->date('start_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
