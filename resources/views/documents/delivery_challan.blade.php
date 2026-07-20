@extends('documents.layout')

@section('title', 'Delivery Challan')

@section('content')
    <h1 class="title">Delivery Challan</h1>
    <p class="muted" style="text-align:center;">
        Challan: {{ $document_number }} · Delivery: {{ $delivery->delivery_number }}
        @if($delivery->booking) · Booking: {{ $delivery->booking->booking_number }} @endif
    </p>

    <div class="section-title">Customer</div>
    <table class="kv">
        <tr><td class="label">Name</td><td>{{ $delivery->customer->name ?? '—' }}</td></tr>
        <tr><td class="label">Mobile</td><td>{{ $delivery->customer->mobile ?? '—' }}</td></tr>
        <tr><td class="label">Branch</td><td>{{ $delivery->branch->name ?? '—' }}</td></tr>
        <tr><td class="label">Delivered On</td><td>{{ optional($delivery->delivered_at)->format('d M Y, h:i A') ?? '—' }}</td></tr>
    </table>

    <div class="section-title">Vehicle</div>
    <table class="kv">
        <tr><td class="label">Vehicle</td><td>{{ $delivery->vehicle->make ?? '' }} {{ $delivery->vehicle->model ?? '' }} {{ $delivery->vehicle->variant ?? '' }}</td></tr>
        <tr><td class="label">Stock No.</td><td>{{ $delivery->vehicle->stock_number ?? '—' }}</td></tr>
        <tr><td class="label">Registration No.</td><td>{{ $delivery->vehicle->registration_number ?? '—' }}</td></tr>
        <tr><td class="label">Colour</td><td>{{ $delivery->vehicle->color ?? '—' }}</td></tr>
        <tr><td class="label">Odometer at Delivery</td><td>{{ $delivery->odometer !== null ? number_format((int) $delivery->odometer).' km' : '—' }}</td></tr>
        <tr><td class="label">Fuel Level</td><td>{{ $delivery->fuel_level ?? '—' }}</td></tr>
    </table>

    <div class="section-title">Items Handed Over</div>
    <table class="kv">
        <tr><td class="label">Keys</td><td>{{ $delivery->dc_keys ? 'Yes' : 'No' }}</td></tr>
        <tr><td class="label">Spare Key</td><td>{{ $delivery->dc_spare_key ? 'Yes' : 'No' }}</td></tr>
        <tr><td class="label">RC Copy</td><td>{{ $delivery->dc_rc_copy ? 'Yes' : 'No' }}</td></tr>
        <tr><td class="label">Insurance</td><td>{{ $delivery->dc_insurance ? 'Yes' : 'No' }}</td></tr>
        <tr><td class="label">Invoice</td><td>{{ $delivery->dc_invoice ? 'Yes' : 'No' }}</td></tr>
        <tr><td class="label">Tool Kit</td><td>{{ $delivery->dc_tool_kit ? 'Yes' : 'No' }}</td></tr>
        <tr><td class="label">Spare Wheel</td><td>{{ $delivery->dc_spare_wheel ? 'Yes' : 'No' }}</td></tr>
        <tr><td class="label">Accessories</td><td>{{ $delivery->dc_accessories ? 'Yes' : 'No' }}</td></tr>
    </table>

    <p style="margin-top:16px; line-height:1.6;">
        I acknowledge receipt of the above pre-owned vehicle along with the listed items in the
        condition inspected. RC ownership transfer will be completed by {{ config('app.name') }}
        as per the agreed process. This is a computer-generated delivery challan.
    </p>

    <table class="signatures">
        <tr>
            <td><div class="sign-line">Customer Signature</div></td>
            <td><div class="sign-line">For {{ config('app.name') }}</div></td>
        </tr>
    </table>
@endsection
