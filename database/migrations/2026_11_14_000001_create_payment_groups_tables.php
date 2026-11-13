<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_groups', function (Blueprint $table) {
            $table->id();
            $table->string('payment_reference', 20)->unique();
            $table->unsignedBigInteger('pc_id')->nullable()->index();
            $table->decimal('total', 12, 2);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamps();

        });

        Schema::create('payment_group_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_group_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['payment_group_id', 'order_id'], 'payment_group_order_unique');
            $table->foreign('payment_group_id')->references('id')->on('payment_groups')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_group_id')->nullable()->after('order_id')->index();
            $table->foreign('payment_group_id')->references('id')->on('payment_groups')->nullOnDelete();
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('matched_payment_group_id')->nullable()->after('matched_order_id')->index();
            $table->foreign('matched_payment_group_id')->references('id')->on('payment_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['matched_payment_group_id']);
            $table->dropColumn('matched_payment_group_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_group_id']);
            $table->dropColumn('payment_group_id');
        });

        Schema::dropIfExists('payment_group_orders');
        Schema::dropIfExists('payment_groups');
    }
};
