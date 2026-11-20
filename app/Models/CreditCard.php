<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditCard extends Model
{
    use HasFactory;


    protected $fillable = [
        'id',
        'account_id',
        'account_number',
        'account_name',
        'type',
        'card_number',
        'cvc',
        'expiry',
        'card_zip',
        'cardnox_token',
        'created_at',
        'updated_at',
        'is_deleted',
    ];


    public function account(){
        return $this->belongsTo(Account::class,'account_id','account_id');
    }

    /**
     * Card data rule: we never store a full card number, a CVC, or a full bank
     * account/routing number. Cards are charged only with the Cardknox token
     * (cardnox_token); the raw number is sent to Cardknox cc:save for
     * tokenizing and is never persisted.
     *
     * Full card number (12-19 digits, spaces/dashes ignored) -> first digit,
     * asterisks, last 4 (e.g. 4***********1234), same format as existing rows.
     * Anything already masked (contains * or x) is kept as is.
     */
    public static function maskCardNumber($number)
    {
        if ($number === null) {
            return null;
        }
        $number = trim((string) $number);
        $digits = preg_replace('/[\s\-]/', '', $number);
        if (preg_match('/^\d{12,19}$/', $digits)) {
            return substr($digits, 0, 1) . str_repeat('*', strlen($digits) - 5) . substr($digits, -4);
        }
        return $number;
    }

    /** Bank account / routing number made only of digits (5+) -> asterisks + last 4. */
    public static function maskBankNumber($number)
    {
        if ($number === null) {
            return null;
        }
        $number = trim((string) $number);
        $digits = preg_replace('/[\s\-]/', '', $number);
        if (preg_match('/^\d{5,}$/', $digits)) {
            return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
        }
        return $number;
    }

    /** True when a submitted card number is the masked one we show (not a newly typed card). */
    public static function isMasked($number)
    {
        return $number !== null && preg_match('/[*xX]/', (string) $number) === 1;
    }

    public function setCardNumberAttribute($value)
    {
        $this->attributes['card_number'] = static::maskCardNumber($value);
    }

    public function setCvcAttribute($value)
    {
        // CVCs must never be stored.
        $this->attributes['cvc'] = null;
    }

    public function setAccountNumberAttribute($value)
    {
        // For cards this column holds the holder name; only all-digit values (ACH) get masked.
        $this->attributes['account_number'] = static::maskBankNumber($value);
    }

    public function setRoutingNumberAttribute($value)
    {
        $this->attributes['routing_number'] = static::maskBankNumber($value);
    }
}
