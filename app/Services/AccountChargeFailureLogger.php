<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records failed attempts to put a trip on an account into account_charge_failures.
 *
 * Logging only. Every public method swallows its own errors, so it can never change or break the
 * payment response. Writes go through a separate DB connection so the row survives the payment
 * code's own transaction being rolled back (or left open). Never stores PINs, card data or tokens.
 */
class AccountChargeFailureLogger
{
    private const CONNECTION = 'account_charge_failure_log';

    public static function isAccountAttempt(Request $request): bool
    {
        return $request->input('payment_method') === 'account'
            || $request->input('payment_method') === 'qr-code'
            || $request->filled('is_qr_payment');
    }

    /** Called with the response TripController::update is about to return. */
    public static function fromResponse(Request $request, $response, array $context = []): void
    {
        try {
            if (!self::isAccountAttempt($request)) {
                return;
            }
            [$failed, $message, $status] = self::readResponse($response);

            if ($failed) {
                if (!empty($context['exception'])) {
                    self::record($request, $context['exception'], $message, 'rejected', $context, $status);
                } else {
                    self::record($request, null, $message, 'rejected', $context, $status);
                }
                return;
            }

            // Went through, but something failed along the way: keep a trace of it.
            if (!empty($context['cardknox_declined'])) {
                self::record($request, null, null, 'accepted', $context, $status, 'paypertrip_card_declined',
                    'Pay-per-trip card declined (Cardknox: ' . $context['cardknox_declined'] . '); the account was set inactive and the trip still went on the account.');
            }
            if (!empty($context['wrong_pin_accepted'])) {
                self::record($request, null, null, 'accepted', $context, $status, 'wrong_pin',
                    'Wrong PIN entered; the trip still went on the account (PIN status saved as Wrong).');
            }
        } catch (\Throwable $e) {
            self::warn($e);
        }
    }

    /** Called when TripController::update throws (the exception is re-thrown by the caller). */
    public static function fromException(Request $request, \Throwable $ex, array $context = []): void
    {
        try {
            if (!self::isAccountAttempt($request)) {
                return;
            }
            self::record($request, $ex, null, 'rejected', $context, 500);
        } catch (\Throwable $e) {
            self::warn($e);
        }
    }

    /** @return array{0: bool, 1: ?string, 2: ?int} [failed, message, http status] */
    private static function readResponse($response): array
    {
        $status = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : null;

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            $message = is_array($data) ? ($data['message'] ?? $data['msg'] ?? null) : null;
            $failed = (is_array($data) && array_key_exists('status', $data) && !$data['status'])
                || ($status !== null && $status >= 400);
            return [$failed, is_string($message) ? $message : null, $status];
        }

        // Redirect / view: a failure is an "error" or "errors" flash set during this request.
        $session = app('session.store');
        $newFlash = (array) $session->get('_flash.new', []);
        if (in_array('error', $newFlash, true)) {
            $message = $session->get('error');
            return [true, is_string($message) ? $message : 'Error', $status];
        }
        if (in_array('errors', $newFlash, true)) {
            $bag = $session->get('errors');
            $message = ($bag && method_exists($bag, 'first')) ? $bag->first() : 'Validation error';
            return [true, $message ?: 'Validation error', $status];
        }

        return [$status !== null && $status >= 500, null, $status];
    }

    private static function record(Request $request, ?\Throwable $ex, ?string $message, string $outcome, array $context, ?int $status, ?string $reasonCode = null, ?string $reasonMessage = null): void
    {
        $db = self::db();

        $tripRef = $request->input('trip');
        $trip = null;
        if ($tripRef !== null && $tripRef !== '') {
            $trip = $db->table('trips')->select('trip_id', 'driver_id', 'trip_cost', 'passenger_phone')
                ->where('trip_id', (string) $tripRef)->first();
            if (!$trip && ctype_digit((string) $tripRef)) {
                $trip = $db->table('trips')->select('trip_id', 'driver_id', 'trip_cost', 'passenger_phone')
                    ->where('id', (int) $tripRef)->first();
            }
        }

        $accountRef = $request->input('account');
        $account = null;
        if ($accountRef !== null && $accountRef !== '') {
            $account = $db->table('accounts')->select('id', 'status')->where('account_id', (string) $accountRef)->first();
        }

        if ($reasonCode === null) {
            [$reasonCode, $reasonMessage] = self::classify($ex, $message, $trip, $account, $tripRef);
        }
        if ($outcome === 'rejected' && !empty($context['cardknox_declined'])) {
            $reasonMessage .= ' (pay-per-trip card was declined first: ' . $context['cardknox_declined'] . ')';
        }
        if ($outcome === 'rejected' && !empty($context['wrong_pin_accepted'])) {
            $reasonMessage .= ' (a wrong PIN was also entered)';
        }

        $driver = Auth::guard('driver')->user();
        $admin = Auth::guard('admin')->user();
        $isAdmin = $request->filled('is_admin') && $admin;

        if ($request->filled('is_qr_payment')) {
            $source = 'qr';
        } elseif ($isAdmin) {
            $source = (($admin->role ?? null) === 'dispatcher') ? 'dispatcher' : 'admin';
        } elseif ($request->filled('is_driver') || $driver) {
            $source = 'driver';
        } else {
            $source = 'web';
        }

        $amount = null;
        $base = ($trip && (float) $trip->trip_cost > 0) ? (float) $trip->trip_cost : (float) $request->input('amount');
        $extra = is_numeric($request->input('extra_charges')) ? (float) $request->input('extra_charges') : 0;
        if ($base > 0 || $extra > 0) {
            $amount = round($base + $extra, 2);
        }

        $phoneDigits = $trip ? preg_replace('/\D/', '', (string) $trip->passenger_phone) : '';

        $db->table('account_charge_failures')->insert([
            'trip_id' => $tripRef !== null ? mb_substr((string) $tripRef, 0, 64) : null,
            'trip_found' => ($tripRef !== null && $tripRef !== '') ? (bool) $trip : null,
            'account_entered' => $accountRef !== null ? mb_substr((string) $accountRef, 0, 64) : null,
            'account_db_id' => $account->id ?? null,
            'driver_id' => $driver->driver_id ?? ($trip->driver_id ?? null),
            'trip_driver_id' => $trip->driver_id ?? null,
            'admin_user_id' => $isAdmin ? $admin->id : null,
            'passenger_phone_last4' => $phoneDigits !== '' ? substr($phoneDigits, -4) : null,
            'attempted_amount' => $amount,
            'reason_code' => $reasonCode,
            'reason_message' => mb_substr(self::scrub((string) $reasonMessage, $request), 0, 500),
            'outcome' => $outcome,
            'source' => $source,
            'channel' => $request->expectsJson() || $request->ajax() ? 'json' : 'form',
            'http_status' => $status,
            'exception_class' => $ex ? mb_substr(get_class($ex), 0, 191) : null,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    /** @return array{0: string, 1: string} */
    private static function classify(?\Throwable $ex, ?string $message, $trip, $account, $tripRef): array
    {
        if ($tripRef !== null && $tripRef !== '' && !$trip) {
            return ['trip_not_found', 'Trip not found' . ($message ? ' (app replied: ' . $message . ')' : '.')];
        }
        if ($ex) {
            if ($ex instanceof \Illuminate\Database\QueryException) {
                return ['exception', 'Database error (SQLSTATE ' . $ex->getCode() . ')'];
            }
            return ['exception', class_basename($ex) . ': ' . $ex->getMessage()];
        }

        $m = strtolower((string) $message);
        $map = [
            'already exists' => 'trip_already_paid',
            'trip not found' => 'trip_not_found',
            'account not match' => 'account_mismatch',
            'pin' => 'invalid_pin',
            'low balance' => 'insufficient_balance',
            'balance' => 'insufficient_balance',
            'no valid credit card' => 'no_card_on_file',
            'address is not allowed' => 'address_restricted',
            'address restriction' => 'address_restricted',
            'phone restriction' => 'phone_restricted',
            'inactive' => 'account_inactive',
            'something went wrong' => 'exception',
            'retry' => 'exception',
            'validation' => 'validation_error',
        ];
        foreach ($map as $needle => $code) {
            if ($m !== '' && str_contains($m, $needle)) {
                return [$code, (string) $message];
            }
        }
        if (str_contains($m, 'account not found')) {
            if (!$account) {
                return ['account_not_found', (string) $message];
            }
            if ((int) $account->status === 0) {
                return ['account_inactive', $message . ' (account exists but is inactive)'];
            }
        }
        return ['other', $message ?: 'Unknown failure'];
    }

    /** Remove anything that could be a PIN or card number from a stored message. */
    private static function scrub(string $text, Request $request): string
    {
        $pin = trim((string) $request->input('account_pin'));
        if (strlen($pin) >= 3) {
            $text = str_replace($pin, '[pin]', $text);
        }
        return preg_replace('/\d{12,19}/', '[number]', $text);
    }

    private static function db()
    {
        $name = self::CONNECTION;
        if (!config("database.connections.$name")) {
            $default = config('database.default');
            config(["database.connections.$name" => config("database.connections.$default")]);
        }
        return DB::connection($name);
    }

    private static function warn(\Throwable $e): void
    {
        try {
            Log::warning('account_charge_failures logging failed: ' . get_class($e) . ' ' . mb_substr($e->getMessage(), 0, 200));
        } catch (\Throwable $ignored) {
        }
    }
}
