<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('messenger_customer_id')->unique()->constrained('messenger_customers')->cascadeOnDelete();
            $table->string('current_step')->nullable();
            // Plain reference (no FK constraint) — the host app's products table
            // name/structure is unknown to this package; see ProductRepository contract.
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('customer_phone')->nullable();
            $table->json('cart_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_sessions');
    }
};
