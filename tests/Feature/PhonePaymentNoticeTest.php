<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Driver;
use App\Models\PhonePayment;
use App\Models\Trip;
use App\Services\PhonePaymentNotice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// CSF-1009: "Paid by phone" notices. Uses qa@example.test test records only; no real charges or texts.
class PhonePaymentNoticeTest extends TestCase
{
    use DatabaseTransactions;

    protected Driver $driver;
    protected Account $account;
    protected Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();
        // Invalid test credentials: Twilio rejects them, so no text can ever be sent from tests.
        config(['app.TWILIO_SID' => 'ACqa00000000000000000000000000000', 'app.TWILIO_AUTH_TOKEN' => 'qa-invalid', 'app.TWILIO_PHONE' => '+15005550006']);

        $this->driver = new Driver();
        $this->driver->driver_id = '9' . rand(100000, 999999);
        $this->driver->first_name = 'QA';
        $this->driver->last_name = 'Tester-9' . rand(100, 999);
        $this->driver->phone = '5005550006';
        $this->driver->status = 1;
        $this->driver->save();

        $this->account = new Account();
        $this->account->account_id = 'QA' . rand(10000, 99999);
        $this->account->f_name = 'QA';
        $this->account->lname = 'Test';
        $this->account->phone = '8455550000';
        $this->account->email = 'qa@example.test';
        $this->account->account_type = 'postpaid';
        $this->account->balance = 0;
        $this->account->status = 1;
        $this->account->pins = '4321';
        $this->account->save();

        $this->trip = new Trip();
        $this->trip->trip_id = '99' . rand(1000000, 9999999);
        $this->trip->driver_id = $this->driver->driver_id;
        $this->trip->passenger_phone = '';
        $this->trip->trip_cost = 12;
        $this->trip->payment_method = 'cash';
        $this->trip->is_delete = 0;
        $this->trip->date = now()->toDateString();
        $this->trip->time = now()->format('H:i:s');
        $this->trip->location_from = 'QA From';
        $this->trip->location_to = 'QA To';
        $this->trip->save();
    }

    public function test_phone_account_payment_records_notice_and_keeps_payment_behaviour(): void
    {
        $res = $this->postJson('/api/twilio/payTripAccount', [
            'account_number' => $this->account->account_id,
            'account_pin' => '4321',
            'trip_id' => $this->trip->trip_id,
        ]);
        $res->assertStatus(200)->assertJson(['move_to' => 1]);

        $trip = Trip::where('trip_id', $this->trip->trip_id)->first();
        $this->assertSame('account', $trip->payment_method);

        $pp = PhonePayment::where('trip_id', $this->trip->trip_id)->first();
        $this->assertNotNull($pp);
        $this->assertSame('account', $pp->method);
        $this->assertSame((string) $this->account->account_id, $pp->account_id);
        $this->assertSame((string) $this->driver->driver_id, $pp->driver_id);
        $this->assertEquals(12.0, $pp->charged_amount);
        $this->assertStringStartsWith('CS-', $pp->reference);
        $this->assertNull($pp->card_last4);
        $this->assertSame(1, \App\Models\Payment::where('trip_id', $this->trip->trip_id)->where('user_type', 'driver')->count());
    }

    public function test_wrong_pin_creates_no_notice(): void
    {
        $this->postJson('/api/twilio/payTripAccount', [
            'account_number' => $this->account->account_id,
            'account_pin' => '0000',
            'trip_id' => $this->trip->trip_id,
        ])->assertJson(['move_to' => 3]);
        $this->assertSame(0, PhonePayment::where('trip_id', $this->trip->trip_id)->count());
    }

    public function test_card_notice_stores_last4_only_and_receipt_text(): void
    {
        $this->trip->passenger_phone = '';
        $pp = PhonePaymentNotice::recordCard($this->trip, [
            'transaction_id' => 'QA123456',
            'masked_card_number' => '4xxxxxxxxxxx1111',
        ], 12.70);
        $this->assertSame('1111', $pp->card_last4);
        $this->assertSame('QA123456', $pp->reference);
        $this->assertEquals(12.70, $pp->charged_amount);
        $this->assertEquals(12.0, $pp->amount);

        $msg = PhonePaymentNotice::receiptMessage($pp);
        $this->assertStringContainsString('Trip #' . $this->trip->trip_id, $msg);
        $this->assertStringContainsString('$12.70', $msg);
        $this->assertStringContainsString('card ending 1111', $msg);
        $this->assertStringContainsString('/receipt/phone/' . $pp->receipt_token, $msg);
        $this->assertDoesNotMatchRegularExpression('/\d{12,}/', $msg);

        PhonePaymentNotice::sendCardReceipt($pp, $this->trip); // no passenger phone -> nothing sent
        $this->assertSame('no_phone', $pp->fresh()->receipt_sms_status);

        $page = $this->get('/receipt/phone/' . $pp->receipt_token);
        $page->assertStatus(200)->assertSee('Paid by phone')->assertSee('ending 1111')->assertSee('$12.70');
        $this->get('/receipt/phone/' . str_repeat('x', 40))->assertStatus(404);
    }

    public function test_driver_sees_and_dismisses_alert_only_for_own_trips(): void
    {
        $pp = PhonePaymentNotice::recordCard($this->trip, ['transaction_id' => 'QA1', 'masked_card_number' => 'xxxx4242'], 12.70);
        $other = PhonePayment::create([
            'trip_id' => '1', 'driver_id' => '1', 'method' => 'card', 'amount' => 5, 'charged_amount' => 5,
            'receipt_token' => \Illuminate\Support\Str::random(40), 'paid_at' => now(),
        ]);

        $this->actingAs($this->driver, 'driver');
        $res = $this->getJson('/phone-payments/unseen')->assertStatus(200);
        $ids = collect($res->json('notices'))->pluck('id')->all();
        $this->assertSame([$pp->id], $ids);
        $this->assertSame('card ending 4242', $res->json('notices.0.method_label'));

        $this->postJson('/phone-payments/seen', ['ids' => [$pp->id, $other->id]])->assertJson(['ok' => true, 'updated' => 1]);
        $this->assertNotNull($pp->fresh()->driver_seen_at);
        $this->assertNull($other->fresh()->driver_seen_at);
        $this->assertSame([], $this->getJson('/phone-payments/unseen')->json('notices'));

        $this->get('/single-trip/' . $this->trip->id)->assertStatus(200)->assertSee('Paid by phone')->assertSee('QA1');
        $this->get('/trip_history?offset=0', ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(200)->assertSee('Paid by phone');
    }
    public function test_account_holder_sees_paid_by_phone_in_portal(): void
    {
        $this->postJson('/api/twilio/payTripAccount', [
            'account_number' => $this->account->account_id,
            'account_pin' => '4321',
            'trip_id' => $this->trip->trip_id,
        ])->assertJson(['move_to' => 1]);
        $pp = PhonePayment::where('trip_id', $this->trip->trip_id)->first();

        $this->actingAs($this->account, 'customer');
        $this->get('/customer')->assertStatus(200)->assertSee('Paid by phone')->assertSee('Trip #' . $this->trip->trip_id)->assertSee($pp->reference);

        $json = $this->getJson('/customer/trips?account_id=' . $this->account->account_id . '&draw=1&start=0&length=10', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(200)->json('data');
        $row = collect($json)->firstWhere('trip_id', $this->trip->trip_id);
        $this->assertNotNull($row);
        $this->assertStringContainsString('Paid by phone', $row['payment_method']);
    }
}
