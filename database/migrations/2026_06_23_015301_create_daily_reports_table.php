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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->enum('report_type', ['daily', 'weekly', 'monthly'])->default('daily');
            
            // Report Content
            $table->text('market_context')->nullable();
            $table->text('setup_summary')->nullable();
            $table->text('trade_outcome')->nullable();
            $table->longText('claude_analysis')->nullable();
            
            // Performance Summary
            $table->json('pnl_summary')->nullable();
            
            $table->timestamps();
            
            $table->index(['report_date', 'report_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
