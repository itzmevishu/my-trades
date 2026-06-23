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
        Schema::create('strategy_configs', function (Blueprint $table) {
            $table->id();
            $table->integer('version')->unique();
            
            // Learning Engine Outputs
            $table->json('pattern_weights')->nullable()->comment('Win rates by pattern type');
            $table->string('best_entry_window', 50)->nullable();
            $table->decimal('min_score_threshold', 4, 2)->default(6.0);
            $table->json('avoid_setups')->nullable()->comment('Patterns/conditions to avoid');
            $table->text('learning_note')->nullable();
            
            // Metadata
            $table->integer('trades_analysed')->default(0);
            $table->decimal('win_rate_at_update', 5, 2)->nullable();
            $table->boolean('is_active')->default(false);
            
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strategy_configs');
    }
};
