<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhonePayment extends Model
{
    protected $table = 'phone_payments';

    protected $guarded = ['id'];

    protected $casts = [
        'paid_at' => 'datetime',
        'driver_seen_at' => 'datetime',
        'amount' => 'float',
        'charged_amount' => 'float',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class, 'trip_id', 'trip_id');
    }

    public function methodLabel(): string
    {
        if ($this->method === 'card') {
            return $this->card_last4 ? 'card ending ' . $this->card_last4 : 'card';
        }
        return 'account';
    }

    public function paidAtLabel(): string
    {
        $t = $this->paid_at ?: $this->created_at;
        return $t ? $t->copy()->timezone('America/New_York')->format('m/d/Y g:i A') : '';
    }

    public function receiptUrl(): string
    {
        return url('/receipt/phone/' . $this->receipt_token);
    }

    public function toNotice(): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'method' => $this->method,
            'method_label' => $this->methodLabel(),
            'amount' => number_format((float) $this->charged_amount, 2),
            'trip_amount' => number_format((float) $this->amount, 2),
            'paid_at' => $this->paidAtLabel(),
            'reference' => $this->reference,
        ];
    }
}
