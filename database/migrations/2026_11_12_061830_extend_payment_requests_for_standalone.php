<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->string('type', 20)->default('application')->after('public_id')->index();
            $table->foreignId('application_id')->nullable()->change();
            $table->string('customer_name', 150)->nullable()->after('application_id');
            $table->string('customer_email', 254)->nullable()->after('customer_name')->index();
            $table->string('customer_phone', 30)->nullable()->after('customer_email');
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropIndex(['customer_email']);
            $table->dropColumn(['type', 'customer_name', 'customer_email', 'customer_phone']);
        });
    }
};
