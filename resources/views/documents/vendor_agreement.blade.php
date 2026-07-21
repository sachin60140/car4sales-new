<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #18181b; font-size: 11.5px; line-height: 1.5; }
        .page { padding: 4px 2px; }
        .page-break { page-break-before: always; }
        h1 { font-size: 17px; color: #7c1d1d; margin: 0 0 2px; text-align: center; }
        h2 { font-size: 13px; margin: 14px 0 6px; border-bottom: 1px solid #d4d4d8; padding-bottom: 3px; }
        .muted { color: #71717a; font-size: 10px; text-align: center; margin-bottom: 10px; }
        table.kv { width: 100%; border-collapse: collapse; margin: 6px 0; }
        table.kv td { padding: 4px 6px; border: 1px solid #e4e4e7; vertical-align: top; }
        table.kv td.label { width: 32%; background: #f4f4f5; font-weight: bold; }
        ol { margin: 4px 0 4px 16px; padding: 0; }
        ol li { margin-bottom: 4px; }
        .sign { width: 100%; margin-top: 34px; }
        .sign td { width: 50%; padding-top: 26px; border-top: 1px solid #52525b; font-size: 10px; text-align: center; }
        .sign-wrap { width: 100%; }
        .sign-wrap td { width: 50%; padding: 0 12px; }
        .formtitle { text-align: center; font-weight: bold; font-size: 13px; margin-bottom: 2px; }
        .formrule { text-align: center; font-size: 9px; color: #71717a; margin-bottom: 10px; }
        .foot { margin-top: 12px; color: #a1a1aa; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    @php
        $veh = trim(($submission->make ?? '').' '.($submission->model ?? '').' '.($submission->variant ?? ''));
        $sellerName = $profile?->company_name ?: ($vendor?->name ?? 'Vendor');
        $amount = number_format((float) $submission->expected_amount, 2);
    @endphp

    {{-- ===== Page 1: Purchase Agreement ===== --}}
    <div class="page">
        <h1>Vehicle Purchase Agreement</h1>
        <div class="muted">Ref: {{ $submission->submission_number }} · Generated {{ $generatedAt->format('d M Y') }}</div>

        <p>This Agreement is made between <strong>{{ $buyerName }}</strong> ("Buyer") and
            <strong>{{ $sellerName }}</strong> ("Seller"), for the sale and purchase of the pre-owned
            vehicle described below, on the terms set out herein.</p>

        <h2>Parties</h2>
        <table class="kv">
            <tr><td class="label">Seller</td><td>{{ $sellerName }}</td></tr>
            <tr><td class="label">Seller Contact</td><td>{{ $profile?->phone ?? $vendor?->phone ?? '—' }} · {{ $vendor?->email ?? '' }}</td></tr>
            <tr><td class="label">Buyer</td><td>{{ $buyerName }}{{ $submission->branch ? ' — '.$submission->branch->name : '' }}</td></tr>
        </table>

        <h2>Vehicle</h2>
        <table class="kv">
            <tr><td class="label">Make / Model / Variant</td><td>{{ $veh ?: '—' }}</td></tr>
            <tr><td class="label">Registration No.</td><td>{{ $submission->registration_number ?? '—' }}</td></tr>
            <tr><td class="label">Mfg. Year</td><td>{{ $submission->manufacturing_year ?? '—' }}</td></tr>
            <tr><td class="label">Fuel / Transmission</td><td>{{ $submission->fuel_type ?? '—' }} / {{ $submission->transmission ?? '—' }}</td></tr>
            <tr><td class="label">Odometer</td><td>{{ $submission->odometer_km ? number_format((int) $submission->odometer_km).' km' : '—' }}</td></tr>
            <tr><td class="label">Ownership Serial</td><td>{{ $submission->ownership_serial ?? '—' }}</td></tr>
        </table>

        <h2>Consideration</h2>
        <table class="kv">
            <tr><td class="label">Agreed Amount</td><td><strong>₹ {{ $amount }}</strong> (Rupees {{ ucwords(\Illuminate\Support\Str::of((string) (int) $submission->expected_amount)) }} only)</td></tr>
        </table>

        <h2>Terms &amp; Conditions</h2>
        <ol>
            <li>The Seller warrants lawful ownership of the vehicle and the right to sell it, free of any lien, hypothecation or encumbrance except as disclosed in writing.</li>
            <li>The vehicle is sold on an as-inspected basis. The Seller has disclosed all known defects in the accompanying condition report.</li>
            <li>The Seller shall hand over the original Registration Certificate, valid insurance, and all keys and documents on completion.</li>
            <li>The Seller shall sign and provide RTO transfer Forms 29 and 30 (enclosed) and any other form required to effect transfer of ownership.</li>
            <li>Payment of the agreed amount shall be made to the Seller's nominated bank account after verification of documents and vehicle.</li>
            <li>Ownership and risk pass to the Buyer on receipt of the vehicle and completed transfer documents.</li>
            <li>Any dispute arising shall be subject to the jurisdiction of the courts at the Buyer's registered branch location.</li>
        </ol>

        <table class="sign-wrap">
            <tr>
                <td>
                    <table class="sign"><tr><td>Seller — {{ $sellerName }}</td></tr></table>
                </td>
                <td>
                    <table class="sign"><tr><td>For {{ $buyerName }}</td></tr></table>
                </td>
            </tr>
        </table>
    </div>

    {{-- ===== Page 2: Form 29 ===== --}}
    <div class="page page-break">
        <div class="formtitle">FORM 29</div>
        <div class="formrule">[See Rules 55(1)] — Notice of Transfer of Ownership of a Motor Vehicle</div>

        <table class="kv">
            <tr><td class="label">Registration No.</td><td>{{ $submission->registration_number ?? '—' }}</td></tr>
            <tr><td class="label">Make &amp; Model</td><td>{{ $veh ?: '—' }}</td></tr>
            <tr><td class="label">Year of Manufacture</td><td>{{ $submission->manufacturing_year ?? '—' }}</td></tr>
            <tr><td class="label">Type of Body</td><td>{{ $submission->transmission ? '—' : '—' }}</td></tr>
            <tr><td class="label">Fuel Used</td><td>{{ $submission->fuel_type ?? '—' }}</td></tr>
            <tr><td class="label">Name of Transferor (Seller)</td><td>{{ $sellerName }}</td></tr>
            <tr><td class="label">Name of Transferee (Buyer)</td><td>{{ $buyerName }}</td></tr>
            <tr><td class="label">Odometer Reading</td><td>{{ $submission->odometer_km ? number_format((int) $submission->odometer_km).' km' : '—' }}</td></tr>
        </table>

        <p style="margin-top:10px;">I/We hereby declare that the ownership of the above vehicle has been
            transferred to the transferee named above with effect from the date of delivery. I/We request
            that the transfer of ownership be recorded accordingly.</p>

        <table class="sign-wrap" style="margin-top:26px;">
            <tr>
                <td><table class="sign"><tr><td>Signature of Transferor (Seller)</td></tr></table></td>
                <td><table class="sign"><tr><td>Signature of Transferee (Buyer)</td></tr></table></td>
            </tr>
        </table>
    </div>

    {{-- ===== Page 3: Form 30 ===== --}}
    <div class="page page-break">
        <div class="formtitle">FORM 30</div>
        <div class="formrule">[See Rules 55(1) &amp; (2)] — Report of Transfer of Ownership of a Motor Vehicle</div>

        <table class="kv">
            <tr><td class="label">Registration No.</td><td>{{ $submission->registration_number ?? '—' }}</td></tr>
            <tr><td class="label">Chassis No.</td><td>________________________</td></tr>
            <tr><td class="label">Engine No.</td><td>________________________</td></tr>
            <tr><td class="label">Make &amp; Model</td><td>{{ $veh ?: '—' }}</td></tr>
            <tr><td class="label">Name of Transferor (Seller)</td><td>{{ $sellerName }}</td></tr>
            <tr><td class="label">Name of Transferee (Buyer)</td><td>{{ $buyerName }}</td></tr>
            <tr><td class="label">Date of Transfer</td><td>{{ $generatedAt->format('d M Y') }}</td></tr>
        </table>

        <p style="margin-top:10px;">The transferor reports the transfer of ownership of the above vehicle to
            the transferee. The transferee confirms acceptance and applies for the entry of transfer of
            ownership in the certificate of registration.</p>

        <table class="sign-wrap" style="margin-top:26px;">
            <tr>
                <td><table class="sign"><tr><td>Signature of Transferor (Seller)</td></tr></table></td>
                <td><table class="sign"><tr><td>Signature of Transferee (Buyer)</td></tr></table></td>
            </tr>
        </table>

        <div class="foot">This is a computer-generated, pre-filled agreement from {{ $buyerName }}. Please review, sign, and return the enclosed forms.</div>
    </div>
</body>
</html>
