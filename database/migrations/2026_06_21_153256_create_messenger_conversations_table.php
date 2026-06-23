<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('messenger_customer_id')->constrained('messenger_customers')->cascadeOnDelete();
            // Plain reference (no FK constraint) — the host app's orders table
            // name/structure is unknown to this package; see OrderCreator contract.
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_conversations');
    }
};
