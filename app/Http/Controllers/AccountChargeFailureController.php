<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin-only list of failed "put on account" attempts (read only). */
class AccountChargeFailureController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('account_charge_failures')->orderByDesc('id');

        if ($request->filled('trip_id')) {
            $query->where('trip_id', trim($request->trip_id));
        }
        if ($request->filled('account')) {
            $query->where('account_entered', trim($request->account));
        }
        if ($request->filled('driver_id')) {
            $query->where('driver_id', (int) $request->driver_id);
        }
        if ($request->filled('reason')) {
            $query->where('reason_code', $request->reason);
        }

        $failures = $query->paginate(50)->withQueryString();
        $reasons = DB::table('account_charge_failures')->select('reason_code')->distinct()->orderBy('reason_code')->pluck('reason_code');

        return view('admin.account_charge_failures', compact('failures', 'reasons'));
    }
}
