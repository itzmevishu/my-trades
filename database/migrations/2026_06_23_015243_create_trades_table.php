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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->enum('direction', ['long', 'short']);
            $table->string('instrument', 50)->default('BANKNIFTY');
            $table->date('expiry');
            $table->integer('strike');
            
            // Entry/Exit Details
            $table->time('entry_time')->nullable();
            $table->time('exit_time')->nullable();
            $table->decimal('entry_premium', 10, 2)->nullable();
            $table->decimal('exit_premium', 10, 2)->nullable();
            
            // Risk Management
            $table->decimal('sl_premium', 10, 2)->nullable();
            $table->decimal('target_premium', 10, 2)->nullable();
            $table->integer('lots');
            $table->decimal('capital_at_trade', 12, 2);
            
            // Context Fields for Learning
            $table->string('candle_pattern', 50)->nullable();
            $table->json('ema_configuration')->nullable();
            $table->enum('htf_bias', ['bullish', 'bearish', 'neutral'])->nullable();
            $table->enum('session_slot', ['11:15-12:00', '12:00-13:00', '13:00-14:00'])->nullable();
            
            // Claude AI Integration
            $table->boolean('is_exception_trade')->default(false);
            $table->decimal('claude_score', 4, 2)->nullable();
            $table->text('claude_reasoning')->nullable();
            
            // Trade Outcome
            $table->enum('outcome', ['win', 'loss', 'breakeven'])->nullable();
            $table->decimal('rr_achieved', 8, 2)->nullable();
            $table->decimal('pnl_points', 10, 2)->nullable();
            $table->decimal('pnl_inr', 12, 2)->nullable();
            
            // Additional Context
            $table->string('market_condition', 100)->nullable();
            $table->text('post_trade_analysis')->nullable();
            $table->enum('status', ['active', 'closed', 'failed'])->default('active');
            $table->string('exit_type', 50)->nullable(); // SL_HIT, TARGET_HIT, EOD_EXIT, PARTIAL_EXIT
            
            // Partial Exit Support
            $table->decimal('partial_exit_premium', 10, 2)->nullable();
            $table->time('partial_exit_time')->nullable();
            $table->integer('lots_remaining')->nullable();
            $table->decimal('pnl_realized', 12, 2)->default(0);
            
            // Mode
            $table->enum('mode', ['paper', 'live'])->default('paper');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['date', 'status']);
            $table->index('outcome');
            $table->index('candle_pattern');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
