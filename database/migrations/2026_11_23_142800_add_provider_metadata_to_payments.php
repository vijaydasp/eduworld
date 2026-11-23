<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_payment_type', 10)->nullable()->after('gateway_reference');
            $table->string('provider_payment_brand', 40)->nullable()->after('provider_payment_type');
            $table->string('provider_result_code', 30)->nullable()->after('provider_payment_brand');
            $table->timestamp('provider_transaction_at')->nullable()->after('provider_result_code');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'provider_payment_type',
                'provider_payment_brand',
                'provider_result_code',
                'provider_transaction_at',
            ]);
        });
    }
};
