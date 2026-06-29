<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bkash_payments', static function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('payment_id')->unique()->comment('bKash paymentID from Create Payment API');
            $table->string('trx_id')->nullable()->comment('bKash transaction ID from Execute Payment API');
            $table->string('transaction_status')->nullable()->comment('bKash transactionStatus e.g. Completed');
            $table->string('merchant_invoice_number')->nullable();
            $table->bigInteger('amount')->nullable()->comment('Amount in minor units (smallest currency unit)');
            $table->string('currency')->nullable();
            $table->string('intent')->nullable()->comment('sale, etc.');
            $table->string('payer_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->string('last_error')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('customer_msisdn')->nullable()->comment('Customer mobile number');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bkash_payments');
    }
};
