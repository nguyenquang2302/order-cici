<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_reference', 40)
                ->nullable()
                ->unique();

            $table->string('payment_method', 30)
                ->default('cash');

            $table->timestamp('payment_expires_at')
                ->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('transaction_code');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['transaction_code']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['payment_reference']);

            $table->dropColumn([
                'payment_reference',
                'payment_method',
                'payment_expires_at',
            ]);
        });
    }
};
