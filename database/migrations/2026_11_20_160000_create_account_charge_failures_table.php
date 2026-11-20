<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log of failed attempts to put a trip on an account (driver payment screen / admin "account" payment).
 * Logging only: nothing reads this table to decide payments. Adds a new table; no existing table changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_charge_failures')) {
            return;
        }
        Schema::create('account_charge_failures', function (Blueprint $table) {
            $table->id();
            $table->string('trip_id', 64)->nullable()->index();        // trip the driver selected (as sent)
            $table->boolean('trip_found')->nullable();
            $table->string('account_entered', 64)->nullable()->index(); // account number typed / scanned
            $table->unsignedBigInteger('account_db_id')->nullable();    // accounts.id when it exists
            $table->unsignedBigInteger('driver_id')->nullable()->index(); // logged-in driver, else the trip's driver
            $table->unsignedBigInteger('trip_driver_id')->nullable();
            $table->unsignedBigInteger('admin_user_id')->nullable();    // admin/dispatcher who tried, if any
            $table->string('passenger_phone_last4', 4)->nullable();
            $table->decimal('attempted_amount', 10, 2)->nullable();
            $table->string('reason_code', 50)->index();
            $table->string('reason_message', 500)->nullable();
            $table->string('outcome', 20)->default('rejected');         // rejected | accepted (went through with a warning)
            $table->string('source', 30)->nullable();                   // driver | admin | dispatcher | qr | web
            $table->string('channel', 10)->nullable();                  // json | form
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('exception_class', 191)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_charge_failures');
    }
};
