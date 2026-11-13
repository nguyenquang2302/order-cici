<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();

            // Mã giao dịch duy nhất từ MB Bank
            $table->string('transaction_code', 100)->unique();

            $table->decimal('amount', 12, 2);
            $table->text('content')->nullable();

            $table->string('payment_reference', 20)->nullable();

            $table->string('transaction_date', 100)->nullable();
            $table->string('account_receiver', 100)->nullable();
            $table->string('gate', 50)->nullable();

            // incoming: giao dịch tiền vào
            $table->string('direction', 20)->default('incoming');

            // Đơn hàng được ghép với giao dịch
            $table->foreignId('matched_order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            // received, paid, already_paid, mismatch,
            // cancelled_order, unmatched, already_recorded
            $table->string('reconciliation_status', 30)
                ->default('received');

            $table->text('note')->nullable();

            // Lưu nguyên dữ liệu MB gửi lên để đối chiếu
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            $table->index('matched_order_id');
            $table->index('reconciliation_status');
            $table->index('payment_reference');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
