<!DOCTYPE html>
<html>
@php
    $brandColor = getBrandColor($invoice->creator ? $invoice->creator->id : createdBy());
@endphp

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #111;
            margin: 0;
            padding: 0;
            font-size: 13px;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .invoice-preview-main {
            width: 100%;
            margin: 0 auto;
            background: #ffffff;
        }

        .invoice-header {
            background: #ffffff;
            color: #111;
            padding: 30px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-cell {
            vertical-align: top;
        }

        .invoice-title {
            text-transform: uppercase;
            font-size: 40px;
            font-weight: bold;
            margin: 0;
            line-height: 1;
            color: #111;
        }

        .company-name {
            margin: 0;
            font-size: 24px;
            color:
                {{ $brandColor }}
            ;
        }

        .invoice-body {
            padding: 30px 25px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            vertical-align: top;
            width: 50%;
        }

        .info-table strong {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
            color: #111;
        }

        .info-table p {
            margin: 0;
            line-height: 1.5;
            color: #333;
        }

        .items-table {
            margin-top: 30px;
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            background: #ffffff;
            color: #111;
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            border-top: 2px solid #111;
            border-bottom: 2px solid #111;
        }

        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            color: #333;
        }

        .items-table .totals-row td {
            border-top: 2px solid #111;
            border-bottom: none;
            font-weight: bold;
            color: #111;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .summary-table td {
            padding: 8px 0;
            text-align: right;
            color: #333;
        }

        .summary-table td.amount-cell {
            width: 146px;
            padding-left: 20px;
            color: #111;
        }
    </style>
</head>

<body>
    @php
        $currencySymbol = getSetting('currency_symbol', '$');

        // Calculations
        $subtotal = 0;
        $totalTax = 0;
        $totalDiscount = 0;
        $totalQuantity = 0;
        $totalRate = 0;
        $taxesData = [];

        foreach ($invoice->products as $product) {
            $qty = $product->pivot->quantity;
            $price = $product->pivot->unit_price;
            $lineTotal = $qty * $price;
            $discountAmt = $product->pivot->discount_amount ?? 0;
            $finalLineTotal = $lineTotal - $discountAmt;

            $subtotal += $finalLineTotal;
            $totalDiscount += $discountAmt;
            $totalQuantity += $qty;
            $totalRate += $lineTotal;

            if ($product->tax) {
                $taxAmt = ($finalLineTotal * $product->tax->rate) / 100;
                $totalTax += $taxAmt;

                if (!isset($taxesData[$product->tax->name])) {
                    $taxesData[$product->tax->name] = 0;
                }
                $taxesData[$product->tax->name] += $taxAmt;
            }
        }

        $grandTotal = $subtotal + $totalTax;

        // Find payments and due amount
        $paidAmount = 0;
        if (isset($invoice->payments)) {
            foreach ($invoice->payments as $payment) {
                if ($payment->status === 'completed') {
                    $paidAmount += $payment->amount;
                }
            }
        }
        $dueAmount = max(0, $grandTotal - $paidAmount);
    @endphp

    <div class="invoice-preview-main">
        <div class="invoice-header">
            <table class="header-table" style="margin-bottom: 30px;">
                <tr>
                    <td class="header-cell">
                        <img src="{{ public_path(getCompanyLogo()) }}" alt="{{ getCompanyName() }}"
                            style="max-width: 200px; max-height: 80px;">
                    </td>
                    <td class="header-cell text-right">
                        <h3 class="invoice-title">INVOICE</h3>
                    </td>
                </tr>
            </table>

            <table class="header-table">
                <tr>
                    <td class="header-cell">
                        <strong>From:</strong>
                        <p>
                            @if($invoice->creator)
                                {{ $invoice->creator->name }}<br>
                                {{ $invoice->creator->email }}
                            @endif
                        </p>
                    </td>
                    <td class="header-cell">
                        <table style="width: 100%;">
                            <tr>
                                <td class="text-right"></td>
                                <td class="text-right" style="padding-left: 10px; color: #333;">Number:
                                    {{ $invoice->invoice_number }}
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td class="text-right" style="padding-left: 10px; color: #333;">Date:
                                    {{ $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('M d, Y') : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td class="text-right" style="padding-left: 10px; color: #333;">Due:
                                    {{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') : '-' }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="invoice-body">
            <table class="info-table">
                <tr>
                    <td>
                        <strong>Bill To:</strong>
                        <p>
                            @if($invoice->account) {{ $invoice->account->name }}<br> @endif
                            @if($invoice->contact)
                                {{ $invoice->contact->name }}<br>
                                {{ $invoice->contact->email }}<br>
                                {{ $invoice->contact->phone }}<br>
                            @endif
                            {{ $invoice->billing_address }}<br>
                            {{ $invoice->billing_city }} {{ $invoice->billing_state }} {{ $invoice->billing_country }}
                        </p>
                    </td>
                    <td class="text-right">
                        @if($invoice->shipping_address)
                            <strong>Ship To:</strong>
                            <p>
                                {{ $invoice->shipping_address }}<br>
                                {{ $invoice->shipping_city }} {{ $invoice->shipping_state }}
                                {{ $invoice->shipping_country }}
                            </p>
                        @endif
                    </td>
                </tr>
            </table>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Rate</th>
                        <th>Tax (%)</th>
                        <th>Discount</th>
                        <th>Price <small style="display:block; font-size: 10px; font-weight:normal; color:#555;">before
                                tax & discount</small></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->products as $product)
                        @php
                            $lineTotal = $product->pivot->quantity * $product->pivot->unit_price;
                            $discountAmt = $product->pivot->discount_amount ?? 0;

                            $taxAmt = 0;
                            if ($product->tax) {
                                $taxAmt = (($lineTotal - $discountAmt) * $product->tax->rate) / 100;
                            }
                        @endphp
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->pivot->quantity }}</td>
                            <td>{{ $currencySymbol }}{{ number_format($product->pivot->unit_price, 2) }}</td>
                            <td>
                                @if($product->tax)
                                    {{ $product->tax->name }} ({{ floatval($product->tax->rate) }}%)<br>
                                    {{ $currencySymbol }}{{ number_format($taxAmt, 2) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $discountAmt > 0 ? $currencySymbol . number_format($discountAmt, 2) : '-' }}</td>
                            <td>{{ $currencySymbol }}{{ number_format($lineTotal, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="totals-row">
                        <td>Total</td>
                        <td>{{ $totalQuantity }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($totalRate, 2) }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($totalTax, 2) }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($totalDiscount, 2) }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($subtotal, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <table style="width: 100%; border: none;">
                <tr>
                    <td style="width: 50%;"></td>
                    <td style="width: 50%;">
                        <table class="summary-table">
                            @if($totalDiscount > 0)
                                <tr>
                                    <td>Discount:</td>
                                    <td class="amount-cell">{{ $currencySymbol }}{{ number_format($totalDiscount, 2) }}</td>
                                </tr>
                            @endif

                            @foreach($taxesData as $taxName => $taxPrice)
                                <tr>
                                    <td>{{ $taxName }}:</td>
                                    <td class="amount-cell">{{ $currencySymbol }}{{ number_format($taxPrice, 2) }}</td>
                                </tr>
                            @endforeach

                            <tr>
                                <td><strong>Grand Total:</strong></td>
                                <td class="amount-cell">
                                    <strong>{{ $currencySymbol }}{{ number_format($grandTotal, 2) }}</strong>
                                </td>
                            </tr>
                            @if($paidAmount > 0)
                                <tr>
                                    <td style="color:#059669;"><strong>Paid Amount:</strong></td>
                                    <td class="amount-cell" style="color:#059669;">
                                        <strong>-{{ $currencySymbol }}{{ number_format($paidAmount, 2) }}</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color:#111;"><strong>Amount Due:</strong></td>
                                    <td class="amount-cell" style="color:#111;">
                                        <strong>{{ $currencySymbol }}{{ number_format($dueAmount, 2) }}</strong>
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </td>
                </tr>
            </table>

        </div>
    </div>
</body>

</html>