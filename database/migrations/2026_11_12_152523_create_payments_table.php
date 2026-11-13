<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('amount', 12, 2);

            $table->string('method', 30)->default('cash');
            $table->string('status', 20)->default('completed');

            $table->string('transaction_code', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
