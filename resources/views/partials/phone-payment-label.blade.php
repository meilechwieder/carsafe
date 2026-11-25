{{-- CSF-1009: "Paid by phone" label. Expects $pp (App\Models\PhonePayment|null). --}}
@if(!empty($pp))
    <span style="display:inline-block;margin:4px 0;padding:2px 8px;border-radius:6px;background:#d1e7dd;color:#0f5132;font-weight:600;">
        Paid by phone: ${{ number_format((float) $pp->charged_amount, 2) }} &middot; {{ $pp->paidAtLabel() }}@if($pp->reference) &middot; Ref: {{ $pp->reference }}@endif
    </span>
@endif
