<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CSF-1009: record of each trip paid through the automated phone system (Twilio call-to-pay),
// used for the driver "Paid by phone" alert/label, the customer portal confirmation and the
// card payer's receipt link. Stores card last 4 only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_payments', function (Blueprint $table) {
            $table->id();
            $table->string('trip_id', 64)->index();
            $table->string('driver_id', 64)->nullable()->index();
            $table->string('method', 16); // account | card
            $table->string('account_id', 64)->nullable()->index();
            $table->decimal('amount', 10, 2);          // trip amount
            $table->decimal('charged_amount', 10, 2);  // amount charged (card includes fee)
            $table->string('reference', 64)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->string('receipt_token', 64)->unique();
            $table->string('receipt_sms_status', 16)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('driver_seen_at')->nullable();
            $table->timestamps();
            $table->index(['driver_id', 'driver_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_payments');
    }
};
