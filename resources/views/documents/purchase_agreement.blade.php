@extends('documents.layout')

@section('title', 'Vehicle Purchase Agreement')

@section('content')
    <h1 class="title">Vehicle Purchase Agreement</h1>
    <p class="muted" style="text-align:center;">Reference: {{ $purchase->purchase_number }} · Lead: {{ $lead->lead_number }}</p>

    <div class="section-title">Seller Details</div>
    <table class="kv">
        <tr><td class="label">Name</td><td>{{ $seller->name ?? $lead->seller_name }}</td></tr>
        <tr><td class="label">Mobile</td><td>{{ $seller->mobile ?? $lead->mobile }}</td></tr>
        <tr><td class="label">Address</td><td>{{ $seller->address ?? $lead->address ?? '—' }}</td></tr>
        @if($seller && $seller->pan_number)
            <tr><td class="label">PAN</td><td>{{ $seller->pan_number }}</td></tr>
        @endif
    </table>

    <div class="section-title">Vehicle Details</div>
    <table class="kv">
        <tr><td class="label">Make / Model</td><td>{{ $lead->make }} {{ $lead->model }} {{ $lead->variant }}</td></tr>
        <tr><td class="label">Registration No.</td><td>{{ $lead->registration_number ?? '—' }}</td></tr>
        <tr><td class="label">Mfg. Year</td><td>{{ $lead->manufacturing_year ?? '—' }}</td></tr>
        <tr><td class="label">Fuel / Transmission</td><td>{{ $lead->fuel_type ?? '—' }} / {{ $lead->transmission ?? '—' }}</td></tr>
        <tr><td class="label">Odometer</td><td>{{ $lead->odometer_km ? number_format($lead->odometer_km).' km' : '—' }}</td></tr>
    </table>

    <div class="section-title">Commercial Terms</div>
    <table class="kv">
        <tr><td class="label">Agreed Purchase Price</td><td><strong>₹ {{ number_format((float) $purchase->agreed_price, 2) }}</strong></td></tr>
        <tr><td class="label">Amount in Words</td><td>{{ ucwords(\Illuminate\Support\Str::of((string) (int) $purchase->agreed_price)) }} Rupees Only</td></tr>
    </table>

    <p style="margin-top:16px; line-height:1.6;">
        The Seller confirms lawful ownership of the above vehicle and agrees to sell it to
        {{ config('app.name') }} for the agreed consideration. The Seller undertakes that the vehicle
        is free from encumbrances except as disclosed, and that all statutory documents will be handed
        over at possession. Payment shall be made as per the agreed schedule against valid receipts.
    </p>

    <table class="signatures">
        <tr>
            <td><div class="sign-line">Seller Signature</div></td>
            <td><div class="sign-line">For {{ config('app.name') }}</div></td>
        </tr>
    </table>
@endsection
