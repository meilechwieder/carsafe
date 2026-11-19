<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhoneRestriction
{
    public const WARNING = "This phone isn't on the account's allowed list";

    public const BLOCK_MESSAGE = 'Trip is not allowed based on your phone restriction.';

    /**
     * Whether this account allows a trip for the given passenger phone.
     * Accounts without phone restrictions (or with an empty list) stay unrestricted.
     * Matching follows the existing TripController check so driver/dispatcher blocks stay the same.
     */
    public static function phoneIsAllowed($account, $phone): bool
    {
        if ($account->is_trip_restricted_by_phone == 1) {
            if (!empty($account->restricted_phones)) {
                $phone = preg_replace('/\D/', '', $phone);
                if (strlen($phone) === 11 && str_starts_with($phone, '1')) {
                    $phone = substr($phone, 1);
                }
                $to_match = $phone;
                $allowed_phones = json_decode($account->restricted_phones, true);

                $matched = in_array($to_match, $allowed_phones);

                if (!$matched) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Administrators on admin routes may proceed with a warning.
     * Drivers and dispatchers may not, even on admin routes.
     */
    public static function roleMayBypassPhoneRestriction($role, bool $isAdminRoute): bool
    {
        return $isAdminRoute && $role === 'admin';
    }

    /**
     * True only for an authenticated administrator on an admin route.
     * Dispatchers share the admin guard but have role "dispatcher" and must stay blocked.
     * Request flags such as is_admin are ignored so they cannot be spoofed.
     */
    public static function isActingAdministrator(?Request $request = null, $adminUser = null): bool
    {
        $request = $request ?: request();
        $user = $adminUser ?? Auth::guard('admin')->user();

        return self::roleMayBypassPhoneRestriction($user->role ?? null, $request->is('admin/*'));
    }
}
