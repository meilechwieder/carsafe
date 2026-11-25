<?php

namespace App\Http\Controllers;

use App\Models\PhonePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// CSF-1009: "Paid by phone" notices (driver dashboard alert, card payer receipt page)
class PhonePaymentController extends Controller
{
    public function driverUnseen()
    {
        $driver = Auth::guard('driver')->user();
        if (!$driver) {
            return response()->json(['notices' => []]);
        }
        $notices = PhonePayment::where('driver_id', (string) $driver->driver_id)
            ->whereNull('driver_seen_at')
            ->where('created_at', '>=', now()->subDays(3))
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($p) => $p->toNotice())
            ->values();

        return response()->json(['notices' => $notices]);
    }

    public function driverSeen(Request $request)
    {
        $driver = Auth::guard('driver')->user();
        if (!$driver) {
            return response()->json(['ok' => false], 401);
        }
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        $updated = 0;
        if ($ids) {
            $updated = PhonePayment::where('driver_id', (string) $driver->driver_id)
                ->whereIn('id', $ids)
                ->whereNull('driver_seen_at')
                ->update(['driver_seen_at' => now()]);
        }
        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    public function receipt($token)
    {
        if (!is_string($token) || strlen($token) < 32) {
            abort(404);
        }
        $payment = PhonePayment::where('receipt_token', $token)->first();
        if (!$payment) {
            abort(404);
        }
        return view('receipt.phone-payment', ['payment' => $payment]);
    }
}
