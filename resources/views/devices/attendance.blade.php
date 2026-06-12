@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Attendance</h2>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
   <div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('devices.Attendance') }}">
            <div class="row">

                <div class="col-md-4">
                    <label class="form-label">Employee ID</label>
                    <input
                        type="text"
                        name="employee_id"
                        class="form-control"
                        value="{{ request('employee_id') }}"
                        placeholder="Enter Employee ID">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input
                        type="date"
                        name="date"
                        class="form-control"
                        value="{{ request('date') }}">
                </div>

                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        Filter
                    </button>

                    <a href="{{ route('devices.Attendance') }}"
                       class="btn btn-secondary ms-2">
                        Reset
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>

    <div class="table-responsive">
        <table class="table table-bordered data-table">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>SN</th>
                    <th>Employee ID</th>
                    <th>Timestamp</th>
                    <th>Status 1</th>
                    <th>Status 2</th>
                    <th>Status 3</th>
                    <th>Status 4</th>
                    <th>Status 5</th>
                </tr>
            </thead>

            <tbody>
                @forelse($attendances as $attendance)
                    <tr>
                        <td>{{ $attendance->id }}</td>
                        <td>{{ $attendance->sn }}</td>
                        <td>{{ $attendance->employee_id }}</td>
                        <td>{{ $attendance->timestamp }}</td>
                        <td>{{ $attendance->status1 }}</td>
                        <td>{{ $attendance->status2 }}</td>
                        <td>{{ $attendance->status3 }}</td>
                        <td>{{ $attendance->status4 }}</td>
                        <td>{{ $attendance->status5 }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center">
                            No attendance records found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center">
        {{ $attendances->appends(request()->query())->links() }}
    </div>

</div>
@endsection