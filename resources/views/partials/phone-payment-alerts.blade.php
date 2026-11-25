{{-- CSF-1009: "Paid by phone" alerts for the driver dashboard. Polls every 30 seconds. --}}
<div id="phone-payment-alerts"></div>
<script>
(function () {
    var box = document.getElementById('phone-payment-alerts');
    if (!box) return;
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }
    function render(notices) {
        if (!notices || !notices.length) { box.innerHTML = ''; return; }
        var html = '';
        notices.forEach(function (n) {
            html += '<div class="alert alert-success d-flex justify-content-between align-items-start phone-payment-alert" role="alert" data-id="' + esc(n.id) + '" style="color:#0f5132;background:#d1e7dd;border:1px solid #badbcc;">' +
                '<div><strong>Paid by phone</strong><br>' +
                'Customer paid <strong>$' + esc(n.amount) + '</strong> by ' + esc(n.method_label) + ' for Trip #' + esc(n.trip_id) + '<br>' +
                '<small>' + esc(n.paid_at) + (n.reference ? ' &middot; Ref: ' + esc(n.reference) : '') + '</small></div>' +
                '<button type="button" class="btn btn-sm btn-light ms-2 phone-payment-ok" data-id="' + esc(n.id) + '">Got it</button>' +
                '</div>';
        });
        box.innerHTML = html;
    }
    function load() {
        fetch('{{ url('phone-payments/unseen') }}', {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'})
            .then(function (r) { return r.ok ? r.json() : {notices: []}; })
            .then(function (d) { render(d.notices); })
            .catch(function () {});
    }
    box.addEventListener('click', function (e) {
        var btn = e.target.closest('.phone-payment-ok');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var alertEl = btn.closest('.phone-payment-alert');
        if (alertEl) alertEl.remove();
        fetch('{{ url('phone-payments/seen') }}', {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ids: [id]})
        }).catch(function () {});
    });
    load();
    setInterval(load, 30000);
})();
</script>
