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
        Schema::create('learning_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('trigger_trade_count')->comment('Number of trades that triggered this cycle');
            $table->integer('trades_analysed');
            
            // Config References
            $table->foreignId('previous_config_id')->nullable()->constrained('strategy_configs')->onDelete('set null');
            $table->foreignId('new_config_id')->nullable()->constrained('strategy_configs')->onDelete('set null');
            
            // Learning Outcomes
            $table->json('changes_summary')->nullable()->comment('What changed from previous config');
            $table->text('claude_full_response');
            
            $table->timestamps();
            
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_logs');
    }
};
