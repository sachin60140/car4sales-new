<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #18181b; font-size: 11px; line-height: 1.45; }
        .page { padding: 4px 2px; }
        .head { text-align: center; margin-bottom: 8px; }
        .company { font-size: 18px; color: #7c1d1d; font-weight: bold; }
        .addr { font-size: 9.5px; color: #52525b; }
        h1 { font-size: 14px; margin: 10px 0 2px; text-align: center; letter-spacing: 1px; }
        .muted { color: #71717a; font-size: 9.5px; text-align: center; margin-bottom: 8px; }
        h2 { font-size: 12px; margin: 12px 0 5px; border-bottom: 1px solid #d4d4d8; padding-bottom: 3px; }
        table.kv { width: 100%; border-collapse: collapse; margin: 4px 0; }
        table.kv td { padding: 3.5px 6px; border: 1px solid #e4e4e7; vertical-align: top; }
        table.kv td.label { width: 26%; background: #f4f4f5; font-weight: bold; }
        table.money { width: 100%; border-collapse: collapse; margin: 4px 0; }
        table.money td { padding: 4px 6px; border: 1px solid #e4e4e7; }
        table.money td.r { text-align: right; }
        table.money tr.total td { background: #f4f4f5; font-weight: bold; }
        ol.terms { margin: 4px 0 4px 15px; padding: 0; font-size: 9.7px; line-height: 1.4; }
        ol.terms li { margin-bottom: 3px; }
        .sign-wrap { width: 100%; margin-top: 30px; }
        .sign-wrap td { width: 50%; padding-top: 26px; border-top: 1px solid #52525b; font-size: 9.5px; text-align: center; vertical-align: bottom; }
        .foot { margin-top: 14px; color: #a1a1aa; font-size: 8.5px; text-align: center; }
        .words { font-size: 10px; font-style: italic; margin-top: 2px; }
    </style>
</head>
<body>
    @php
        $veh = trim(($vehicle->make ?? '').' '.($vehicle->model ?? '').' '.($vehicle->variant ?? ''));
        $money = fn ($v) => '₹ '.number_format((float) $v, 2);
        $jurisdiction = $company['jurisdiction'] ?? 'the local courts';
        $companyName = $company['company_name'] ?? config('app.name');
    @endphp

    <div class="page">
        <div class="head">
            <div class="company">{{ $companyName }}</div>
            <div class="addr">{{ $company['address'] ?? '' }}</div>
            <div class="addr">
                Ph: {{ $company['phone'] ?? '' }} · {{ $company['email'] ?? '' }}
                @if (!empty($company['gstin'])) · GSTIN: {{ $company['gstin'] }} @endif
            </div>
        </div>

        <h1>VEHICLE BOOKING SLIP</h1>
        <div class="muted">
            Booking No. <strong>{{ $booking->booking_number }}</strong>
            · Date: {{ $generatedAt->format('d M Y, h:i A') }}
            · Branch: {{ $booking->branch?->name ?? '—' }}
        </div>

        <h2>Customer</h2>
        <table class="kv">
            <tr>
                <td class="label">Name</td><td>{{ $customer?->name ?? '—' }}</td>
                <td class="label">Mobile</td><td>{{ $customer?->mobile ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Customer Code</td><td>{{ $customer?->customer_code ?? '—' }}</td>
                <td class="label">City</td><td>{{ $customer?->city ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Address</td><td colspan="3">{{ $customer?->address ?? '—' }}</td>
            </tr>
        </table>

        <h2>Vehicle</h2>
        <table class="kv">
            <tr>
                <td class="label">Vehicle</td><td>{{ $veh ?: '—' }} ({{ $vehicle->manufacturing_year ?? '—' }})</td>
                <td class="label">Stock No.</td><td>{{ $vehicle->stock_number ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Registration</td><td>{{ $vehicle->registration_number ?? 'Unregistered' }}</td>
                <td class="label">Fuel / Trans.</td><td>{{ $vehicle->fuel_type ?? '—' }} / {{ $vehicle->transmission ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Chassis No.</td><td>{{ $vehicle->chassis_number ?? '—' }}</td>
                <td class="label">Engine No.</td><td>{{ $vehicle->engine_number ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Colour</td><td>{{ $vehicle->color ?? '—' }}</td>
                <td class="label">Odometer</td><td>{{ $vehicle->odometer_km ? number_format($vehicle->odometer_km).' km' : '—' }}</td>
            </tr>
        </table>

        <h2>Booking &amp; Payment</h2>
        <table class="money">
            <tr><td>Agreed selling price</td><td class="r">{{ $money($booking->selling_price) }}</td></tr>
            @if ((float) $booking->discount_amount > 0)
                <tr><td>Less: Discount</td><td class="r">− {{ $money($booking->discount_amount) }}</td></tr>
            @endif
            @if ((float) $booking->exchange_adjustment > 0)
                <tr><td>Less: Exchange adjustment</td><td class="r">− {{ $money($booking->exchange_adjustment) }}</td></tr>
            @endif
            <tr class="total"><td>Net payable</td><td class="r">{{ $money($netPayable) }}</td></tr>
            <tr><td>Booking / token amount received</td><td class="r">{{ $money($paidAmount) }}</td></tr>
            <tr class="total"><td>Balance payable before delivery</td><td class="r">{{ $money($balance) }}</td></tr>
        </table>
        @if ($amountInWords)
            <div class="words">Amount received (in words): {{ $amountInWords }}.</div>
        @endif
        <table class="kv" style="margin-top:6px;">
            <tr>
                <td class="label">Payment mode</td><td>{{ ucfirst($booking->payment_mode) }}</td>
                <td class="label">Delivery (promised)</td>
                <td>{{ $booking->delivery_promised_at ? $booking->delivery_promised_at->format('d M Y') : 'To be confirmed' }}</td>
            </tr>
            <tr>
                <td class="label">Sales Executive</td><td>{{ $booking->salesExecutive?->name ?? '—' }}</td>
                <td class="label">Status</td><td>{{ $booking->status->label() }}</td>
            </tr>
        </table>

        <h2>Terms &amp; Conditions</h2>
        <ol class="terms">
            <li>This booking slip is a receipt for the booking/token amount and an agreement to purchase the above pre-owned vehicle. The booking amount is an advance and is adjusted against the total sale consideration.</li>
            <li>The vehicle is a <strong>used / pre-owned vehicle</strong> and is sold strictly on an <strong>“as-is-where-is” and “no-warranty” basis</strong>. The Buyer confirms having inspected (and/or test-driven) the vehicle and is fully satisfied with its condition, fitness, mileage and documents. No implied warranty of merchantability or fitness under the Sale of Goods Act, 1930 shall apply, save for any separate written warranty issued by the Company.</li>
            <li>The <strong>balance amount is payable in full before delivery</strong>. Delivery shall be made only after realisation of full payment and completion of documentation/finance formalities. Any cheque/UPI/NEFT is subject to realisation.</li>
            <li>Ownership and possession pass to the Buyer only on delivery against full payment. Until then the vehicle remains the property of the Company.</li>
            <li>Transfer of registration/ownership shall be carried out as per the <strong>Motor Vehicles Act, 1988</strong> and the Central Motor Vehicles Rules, 1989. RTO transfer charges, road tax, smart-card, hypothecation/termination and other statutory charges are <strong>payable by the Buyer</strong> unless expressly agreed in writing.</li>
            <li>The Buyer shall be responsible for insurance transfer/renewal from the date of delivery. The Company is not liable for any claim arising after delivery.</li>
            <li>From the date/time of delivery, the Buyer is solely responsible for the vehicle’s use, road-safety compliance, traffic challans, penalties and any third-party liability. The Company shall not be liable for any accident, loss, or violation thereafter.</li>
            <li>Prices are inclusive of applicable taxes as per prevailing GST law; any statutory levy revised by the government shall be borne by the Buyer.</li>
            <li><strong>Cancellation &amp; refund:</strong> if the Buyer cancels the booking, the booking/token amount may be refunded or forfeited as per the Company’s cancellation policy and the reason for cancellation. Refunds, where approved, are processed to the original payment source within a reasonable period.</li>
            <li>Delivery timelines are indicative and subject to document verification, finance/loan approval and RTO processes; delays on these accounts shall not be a ground for cancellation or compensation.</li>
            <li>This slip supersedes oral representations. Any addition/alteration is valid only if recorded in writing and signed by an authorised signatory of the Company.</li>
            <li>This agreement is governed by the laws of India. All disputes are subject to the <strong>exclusive jurisdiction of the courts at {{ $jurisdiction }}</strong>.</li>
        </ol>

        <p style="font-size:9.5px; margin-top:6px;">
            I/We have read, understood and accepted the above terms &amp; conditions and confirm the vehicle and booking details stated herein.
        </p>

        <table class="sign-wrap">
            <tr>
                <td>Buyer’s Signature<br><span style="color:#71717a;">{{ $customer?->name ?? '' }}</span></td>
                <td>For {{ $companyName }}<br><span style="color:#71717a;">Authorised Signatory</span></td>
            </tr>
        </table>

        <div class="foot">
            Computer-generated booking slip — {{ $booking->booking_number }} · generated {{ $generatedAt->format('d M Y H:i') }}.
            This is not a tax invoice.
        </div>
    </div>
</body>
</html>
