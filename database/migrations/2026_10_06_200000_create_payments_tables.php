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
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_reference', 40)->nullable()->unique();
            $table->string('entered_reference', 100)->nullable();
            $table->string('gateway', 50)->default('disabled');
            $table->string('gateway_reference', 150)->nullable();
            $table->string('channel', 20)->default('gateway')->index();
            $table->string('customer_name', 150);
            $table->string('phone', 30)->index();
            $table->string('email')->nullable()->index();
            $table->string('service', 50)->index();
            $table->decimal('amount', 12, 3);
            $table->decimal('amount_received', 12, 3)->default(0);
            $table->char('currency', 3)->default('BHD');
            $table->string('status', 30)->default('pending')->index();
            $table->text('remarks')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('offline_reference', 150)->nullable();
            $table->timestamps();
            $table->index('created_at');
            $table->unique(['gateway', 'gateway_reference']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 50);
            $table->string('event_id', 150);
            $table->char('fingerprint', 64);
            $table->string('status', 30);
            $table->string('result', 30);
            $table->timestamps();
            $table->unique(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};
