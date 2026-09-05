@extends('pdf.layout')

@section('title', $title)
@section('period', $period)

@section('content')
    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">No data for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
