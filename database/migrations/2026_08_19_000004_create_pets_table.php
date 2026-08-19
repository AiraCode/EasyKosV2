<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name')->default('Moni');
            $table->integer('level')->default(1);
            $table->integer('exp')->default(0);
            $table->integer('max_exp')->default(100);
            $table->integer('health')->default(100);
            $table->integer('hunger')->default(100); // 0 - 100%
            $table->integer('happiness')->default(100); // 0 - 100%
            $table->string('mood')->default('happy');
            $table->timestamp('last_fed_at')->nullable();
            $table->timestamp('last_played_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
