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
        Schema::create('market_calendar', function (Blueprint $table) {
            $table->id();
            $table->date('event_date')->unique();
            $table->string('event_type', 100); // expiry, rbi_policy, budget, holiday, etc.
            $table->text('description');
            $table->enum('action', ['skip', 'caution'])->default('caution');
            
            $table->timestamps();
            
            $table->index('event_date');
            $table->index(['event_date', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_calendar');
    }
};
