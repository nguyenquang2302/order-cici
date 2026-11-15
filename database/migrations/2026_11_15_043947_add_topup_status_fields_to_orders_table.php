<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('topup_status', 20)
                ->default('pending')
                ->after('topup_amount');

            $table->timestamp('topup_completed_at')
                ->nullable()
                ->after('topup_status');

            $table->unsignedBigInteger('topup_completed_by')
                ->nullable()
                ->after('topup_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'topup_status',
                'topup_completed_at',
                'topup_completed_by',
            ]);
        });
    }
};
