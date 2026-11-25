<?php

namespace App\Services;

use App\Models\PhonePayment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CSF-1009: "Paid by phone" notices for trips paid through the automated phone system.
 * Recording never affects the payment itself: every step is wrapped and only logged on failure.
 */
class PhonePaymentNotice
{
    public static function recordAccount($trip, $account, $payData = null): ?PhonePayment
    {
        try {
            return PhonePayment::create([
                'trip_id' => (string) $trip->trip_id,
                'driver_id' => $trip->driver_id !== null ? (string) $trip->driver_id : null,
                'method' => 'account',
                'account_id' => $account ? (string) $account->account_id : null,
                'amount' => round((float) $trip->trip_cost, 2),
                'charged_amount' => round((float) $trip->trip_cost, 2),
                'reference' => $payData && $payData->id ? 'CS-' . $payData->id : null,
                'receipt_token' => Str::random(40),
                'paid_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('CSF-1009 phone payment notice (account) not recorded: ' . $e->getMessage());
            return null;
        }
    }

    public static function recordCard($trip, array $charge, $chargedAmount): ?PhonePayment
    {
        try {
            return PhonePayment::create([
                'trip_id' => (string) $trip->trip_id,
                'driver_id' => $trip->driver_id !== null ? (string) $trip->driver_id : null,
                'method' => 'card',
                'account_id' => null,
                'amount' => round((float) $trip->trip_cost, 2),
                'charged_amount' => round((float) $chargedAmount, 2),
                'reference' => isset($charge['transaction_id']) ? (string) $charge['transaction_id'] : null,
                'card_last4' => self::lastFour($charge['masked_card_number'] ?? null),
                'receipt_token' => Str::random(40),
                'paid_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('CSF-1009 phone payment notice (card) not recorded: ' . $e->getMessage());
            return null;
        }
    }

    public static function lastFour($masked): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $masked);
        return strlen($digits) >= 4 ? substr($digits, -4) : null;
    }

    public static function receiptMessage(PhonePayment $pp): string
    {
        $msg = 'CarSafe receipt: Trip #' . $pp->trip_id . ' paid $' . number_format((float) $pp->charged_amount, 2);
        if ($pp->card_last4) {
            $msg .= ' by card ending ' . $pp->card_last4;
        }
        $msg .= ' on ' . $pp->paidAtLabel() . '. Receipt: ' . $pp->receiptUrl();
        return $msg;
    }

    /** Text the card payer (trip passenger phone) a receipt link. Called after the payment is committed. */
    public static function sendCardReceipt(?PhonePayment $pp, $trip): void
    {
        if (!$pp) {
            return;
        }
        try {
            $phone = preg_replace('/[^0-9]/', '', (string) $trip->passenger_phone);
            if (strlen($phone) === 11 && Str::startsWith($phone, '1')) {
                $phone = substr($phone, 1);
            }
            if (strlen($phone) !== 10) {
                $pp->update(['receipt_sms_status' => 'no_phone']);
                return;
            }
            TwilioService::sendRawSms('+1' . $phone, self::receiptMessage($pp));
            $pp->update(['receipt_sms_status' => 'sent']);
        } catch (\Throwable $e) {
            Log::warning('CSF-1009 card receipt text not sent: ' . $e->getMessage());
            try {
                $pp->update(['receipt_sms_status' => 'failed']);
            } catch (\Throwable $ignored) {
            }
        }
    }
}
