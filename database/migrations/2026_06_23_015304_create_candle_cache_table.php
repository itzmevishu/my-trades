<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('candle_cache', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 50);
            $table->enum('timeframe', ['15m', '1D', '1W', '1M']);
            
            // OHLC Data
            $table->decimal('open', 10, 2);
            $table->decimal('high', 10, 2);
            $table->decimal('low', 10, 2);
            $table->decimal('close', 10, 2);
            $table->bigInteger('volume')->default(0);
            
            $table->timestamp('candle_timestamp')->index();
            $table->timestamps();
            
            // Unique constraint to prevent duplicate candles
            $table->unique(['symbol', 'timeframe', 'candle_timestamp']);
            
            // Indexes for fast queries
            $table->index(['symbol', 'timeframe', 'candle_timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candle_cache');
    }
};
