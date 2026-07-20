@extends('documents.layout')

@section('title', 'Sale Invoice')

@section('content')
    <h1 class="title">Tax Invoice</h1>
    <p class="muted" style="text-align:center;">Invoice: {{ $invoice->invoice_number }} · Booking: {{ $booking->booking_number }}</p>

    <div class="section-title">Customer</div>
    <table class="kv">
        <tr><td class="label">Name</td><td>{{ $booking->customer->name ?? '—' }}</td></tr>
        <tr><td class="label">Mobile</td><td>{{ $booking->customer->mobile ?? '—' }}</td></tr>
        <tr><td class="label">Branch</td><td>{{ $booking->branch->name ?? '—' }}</td></tr>
    </table>

    <div class="section-title">Vehicle</div>
    <table class="kv">
        <tr><td class="label">Vehicle</td><td>{{ $booking->vehicle->make ?? '' }} {{ $booking->vehicle->model ?? '' }} {{ $booking->vehicle->variant ?? '' }}</td></tr>
        <tr><td class="label">Stock No.</td><td>{{ $booking->vehicle->stock_number ?? '—' }}</td></tr>
        <tr><td class="label">Registration No.</td><td>{{ $booking->vehicle->registration_number ?? '—' }}</td></tr>
        <tr><td class="label">Mfg. Year</td><td>{{ $booking->vehicle->manufacturing_year ?? '—' }}</td></tr>
    </table>

    <div class="section-title">Commercial Summary</div>
    <table class="kv">
        <tr><td class="label">Selling Price</td><td>₹ {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        <tr><td class="label">Less: Discount</td><td>₹ {{ number_format((float) $invoice->discount, 2) }}</td></tr>
        <tr><td class="label">Less: Exchange</td><td>₹ {{ number_format((float) $booking->exchange_adjustment, 2) }}</td></tr>
        <tr><td class="label"><strong>Invoice Total</strong></td><td><strong>₹ {{ number_format((float) $invoice->total, 2) }}</strong></td></tr>
        <tr><td class="label">Amount in Words</td><td>{{ ucwords(\Illuminate\Support\Str::of((string) (int) $invoice->total)) }} Rupees Only</td></tr>
        <tr><td class="label">Payment Mode</td><td>{{ ucfirst($booking->payment_mode) }}</td></tr>
    </table>

    <p style="margin-top:16px; line-height:1.6;">
        This invoice is issued by {{ config('app.name') }} for the sale of the above pre-owned vehicle.
        The vehicle is sold on an as-inspected basis. Statutory RC transfer will be completed as per
        the agreed process. This is a computer-generated invoice.
    </p>

    <table class="signatures">
        <tr>
            <td><div class="sign-line">Customer Signature</div></td>
            <td><div class="sign-line">For {{ config('app.name') }}</div></td>
        </tr>
    </table>
@endsection
