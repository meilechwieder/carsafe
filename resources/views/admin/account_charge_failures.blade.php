@extends('admin.admin-layout')
@section('content')
    <div class="container-fluid pt-3">
        <div class="card">
            <div class="card-header pb-0">
                <h5>Failed "put on account" attempts</h5>
                <p class="mb-0 text-muted">Newest first. Times are New York time. "Went through" rows are attempts where something failed but the trip still went on the account.</p>
            </div>
            <div class="card-body">
                <form method="get" class="row g-2 mb-3">
                    <div class="col-md-2"><input class="form-control" name="trip_id" placeholder="Trip ID" value="{{ request('trip_id') }}"></div>
                    <div class="col-md-2"><input class="form-control" name="account" placeholder="Account #" value="{{ request('account') }}"></div>
                    <div class="col-md-2"><input class="form-control" name="driver_id" placeholder="Driver ID" value="{{ request('driver_id') }}"></div>
                    <div class="col-md-3">
                        <select class="form-control" name="reason">
                            <option value="">All reasons</option>
                            @foreach($reasons as $r)
                                <option value="{{ $r }}" @selected(request('reason') === $r)>{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary" type="submit">Filter</button>
                        <a class="btn btn-light" href="{{ url()->current() }}">Clear</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                        <tr>
                            <th>When (ET)</th><th>Trip ID</th><th>Account</th><th>Driver</th><th>Amount</th>
                            <th>Reason</th><th>Message</th><th>Result</th><th>Source</th><th>IP</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($failures as $f)
                            <tr>
                                <td class="text-nowrap">{{ $f->created_at ? \Carbon\Carbon::parse($f->created_at, config('app.timezone'))->setTimezone('America/New_York')->format('m/d/Y g:i:s A') : '' }}</td>
                                <td>{{ $f->trip_id }}@if($f->trip_found === 0) <span class="text-danger">(not found)</span>@endif</td>
                                <td>{{ $f->account_entered }}</td>
                                <td>{{ $f->driver_id }}@if($f->admin_user_id) <br><small>admin user {{ $f->admin_user_id }}</small>@endif</td>
                                <td>{{ $f->attempted_amount !== null ? '$' . number_format($f->attempted_amount, 2) : '' }}</td>
                                <td><code>{{ $f->reason_code }}</code></td>
                                <td>{{ $f->reason_message }}</td>
                                <td>{{ $f->outcome === 'accepted' ? 'Went through' : 'Rejected' }}</td>
                                <td>{{ $f->source }} / {{ $f->channel }}</td>
                                <td>{{ $f->ip }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center">No failed attempts recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $failures->links('pagination::bootstrap-4') }}
            </div>
        </div>
    </div>
@endsection
