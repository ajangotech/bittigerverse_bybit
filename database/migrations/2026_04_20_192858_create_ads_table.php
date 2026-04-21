<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('ads_id')->unique();
            $table->string('pair');
            $table->tinyInteger('price_type')->default(0);
            $table->decimal('price', 18, 3)->nullable();
            $table->integer('premium')->nullable();
            $table->decimal('min_amount', 18, 4)->nullable();
            $table->decimal('max_amount', 18, 4)->nullable();
            $table->string('remark')->nullable();
            $table->string('action_type')->nullable(); 
            $table->integer('quantity')->nullable();
            $table->integer('payment_period')->nullable();
            $table->json('payment_methods')->nullable();
            $table->json('trading_preference_set')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
