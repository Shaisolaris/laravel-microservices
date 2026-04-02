<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('customer_id')->index();
            $table->string('status')->default('placed'); $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2); $table->decimal('total', 10, 2);
            $table->text('shipping_address'); $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable(); $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id'); $table->string('name');
            $table->integer('quantity'); $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('order_items'); Schema::dropIfExists('orders'); }
};
