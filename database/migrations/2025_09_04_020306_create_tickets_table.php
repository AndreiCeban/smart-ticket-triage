<?php

/**
 * TODO: Tickets Table Migration
 * 
 * Requirements from specification:
 * - id (ULID) - primary key
 * - subject string - ticket subject
 * - body text - ticket description  
 * - status enum - open, in_progress, resolved, closed
 * - Additional fields for AI classification: category, confidence, explanation, note, manually_categorized
 */

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
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('subject');
            $table->text('body');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->string('category')->nullable();
            $table->decimal('confidence', 3, 2)->nullable(); // 0.00 to 1.00
            $table->text('explanation')->nullable();
            $table->text('note')->nullable();
            $table->boolean('manually_categorized')->default(false);
            $table->timestamps();
            
            $table->index(['status']);
            $table->index(['category']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
