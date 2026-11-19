<?php

namespace Tests\Unit;

use App\Services\PhoneRestriction;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Pure unit tests (no Laravel app / no database).
 */
class PhoneRestrictionTest extends BaseTestCase
{
    private function account($restricted, $phones)
    {
        $account = new \stdClass();
        $account->is_trip_restricted_by_phone = $restricted;
        $account->restricted_phones = $phones;
        return $account;
    }

    public function test_unrestricted_account_allows_any_phone(): void
    {
        $account = $this->account(0, json_encode(['8455361747']));
        $this->assertTrue(PhoneRestriction::phoneIsAllowed($account, '8455009914'));
        $this->assertTrue(PhoneRestriction::phoneIsAllowed($account, '8455361747'));
    }

    public function test_restriction_flag_with_empty_list_allows_any_phone(): void
    {
        $account = $this->account(1, null);
        $this->assertTrue(PhoneRestriction::phoneIsAllowed($account, '8455009914'));

        $account = $this->account(1, '');
        $this->assertTrue(PhoneRestriction::phoneIsAllowed($account, '8455009914'));
    }

    public function test_restricted_account_allows_listed_phone(): void
    {
        $account = $this->account(1, json_encode(['8455361747', '8451112222']));
        $this->assertTrue(PhoneRestriction::phoneIsAllowed($account, '8455361747'));
        $this->assertTrue(PhoneRestriction::phoneIsAllowed($account, '(845) 536-1747'));
        $this->assertTrue(PhoneRestriction::phoneIsAllowed($account, '18455361747'));
    }

    public function test_restricted_account_rejects_unlisted_phone(): void
    {
        $account = $this->account(1, json_encode(['8455361747']));
        $this->assertFalse(PhoneRestriction::phoneIsAllowed($account, '8455009914'));
        $this->assertFalse(PhoneRestriction::phoneIsAllowed($account, '18455009914'));
        $this->assertFalse(PhoneRestriction::phoneIsAllowed($account, ''));
    }

    public function test_only_administrators_on_admin_routes_may_bypass(): void
    {
        $this->assertTrue(PhoneRestriction::roleMayBypassPhoneRestriction('admin', true));
        $this->assertFalse(PhoneRestriction::roleMayBypassPhoneRestriction('admin', false));
        $this->assertFalse(PhoneRestriction::roleMayBypassPhoneRestriction('dispatcher', true));
        $this->assertFalse(PhoneRestriction::roleMayBypassPhoneRestriction('dispatcher', false));
        $this->assertFalse(PhoneRestriction::roleMayBypassPhoneRestriction('driver', true));
        $this->assertFalse(PhoneRestriction::roleMayBypassPhoneRestriction(null, true));
        $this->assertFalse(PhoneRestriction::roleMayBypassPhoneRestriction('', true));
    }
}
