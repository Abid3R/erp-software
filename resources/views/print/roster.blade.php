@extends('print.layout')

@section('title', 'Duty Roster — '.$roster->name)
@section('meta', $roster->start_date->format('d M Y').' to '.$roster->end_date->format('d M Y').' · '.ucfirst($roster->status->value))

@section('content')
    <div style="overflow-x: auto;">
        <table style="font-size: 10px;">
            <thead>
                <tr>
                    <th style="text-align: left;">Employee</th>
                    @foreach ($dates as $date)
                        <th style="text-align: center;">{{ \Illuminate\Support\Carbon::parse($date)->format('D d/m') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($employees as $employee)
                    <tr>
                        <td style="text-align: left; white-space: nowrap;">{{ $employee->employee_code }} — {{ $employee->fullName() }}</td>
                        @foreach ($dates as $date)
                            @php($cell = $grid[$employee->id][$date] ?? '—')
                            <td style="text-align: center; {{ $cell === 'OFF' ? 'color:#999;' : '' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p style="margin-top: 12px; font-size: 10px; color: #666;">Tip: print in landscape for wider date ranges.</p>
@endsection
