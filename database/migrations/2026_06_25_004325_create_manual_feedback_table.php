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
        Schema::create('manual_feedback', function (Blueprint $table) {
            $table->id();
            $table->date('feedback_date');
            $table->time('feedback_time');
            
            // Optional links to trades or scans
            $table->foreignId('trade_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('scan_log_id')->nullable()->constrained()->onDelete('set null');
            
            // Categorization
            $table->enum('category', ['pattern', 'market', 'timing', 'exit', 'risk', 'general'])->default('general');
            $table->enum('importance', ['low', 'medium', 'high'])->default('medium');
            
            // Your observation
            $table->text('note');
            
            // Learning status
            $table->boolean('incorporated_in_learning')->default(false);
            $table->foreignId('learning_log_id')->nullable()->constrained()->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index('feedback_date');
            $table->index('category');
            $table->index('incorporated_in_learning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_feedback');
    }
};
