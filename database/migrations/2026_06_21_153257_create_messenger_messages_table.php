<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('messenger_conversation_id')->constrained('messenger_conversations')->cascadeOnDelete();
            $table->string('message_id')->nullable()->unique();
            $table->string('sender_type');
            $table->text('message_text')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_messages');
    }
};
