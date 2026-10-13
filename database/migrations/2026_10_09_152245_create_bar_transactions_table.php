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
        Schema::create('bar_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_member_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->string('type');
            $table->string('category');
            $table->string('description');
            $table->unsignedInteger('amount');
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['type', 'occurred_at']);
            $table->index(['staff_member_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_transactions');
    }
};
