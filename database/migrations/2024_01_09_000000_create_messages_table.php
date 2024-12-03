<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained('users')->onDelete('cascade');
            $table->text('content');
            $table->boolean('read')->default(false);
            $table->timestamps();

            // Index for faster message retrieval
            $table->index(['sender_id', 'recipient_id']);
            $table->index(['recipient_id', 'read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
}; 