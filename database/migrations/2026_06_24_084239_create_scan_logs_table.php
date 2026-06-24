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
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->date('scan_date');
            $table->time('scan_time');
            $table->enum('result', ['no_pattern', 'rejected_ema', 'rejected_score', 'trade_taken', 'already_traded', 'outside_window'])->index();
            
            // Pattern info (if detected)
            $table->string('pattern_detected', 50)->nullable();
            $table->string('pattern_direction', 20)->nullable();
            $table->decimal('current_price', 10, 2)->nullable();
            
            // EMA data
            $table->decimal('ema_20', 10, 2)->nullable();
            $table->decimal('ema_100', 10, 2)->nullable();
            $table->decimal('ema_200', 10, 2)->nullable();
            $table->integer('ema_confluence_count')->default(0);
            
            // Claude scoring (if reached)
            $table->decimal('claude_score', 3, 1)->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Link to trade (if taken)
            $table->foreignId('trade_id')->nullable()->constrained('trades')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes for reporting
            $table->index(['scan_date', 'result']);
            $table->index('scan_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
