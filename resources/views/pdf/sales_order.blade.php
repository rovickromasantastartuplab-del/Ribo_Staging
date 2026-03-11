<!DOCTYPE html>
<html>
@php
    $brandColor = getBrandColor($salesOrder->creator ? $salesOrder->creator->id : createdBy());
@endphp
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Sales Order {{ $salesOrder->order_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
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

        .quote-title {
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
        $currencyCode = getSetting('defaultCurrency', 'USD');
        $currencyModel = \App\Models\Currency::where('code', $currencyCode)->first();
        $currencySymbol = $currencyModel ? $currencyModel->symbol : '₱';

        // Calculations
        $subtotal = 0;
        $totalTax = 0;
        $totalDiscount = 0;
        $totalQuantity = 0;
        $totalRate = 0;
        $taxesData = [];

        foreach ($salesOrder->products as $product) {
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
                        <h3 class="quote-title">SALES ORDER</h3>
                    </td>
                </tr>
            </table>

            <table class="header-table">
                <tr>
                    <td class="header-cell">
                        <strong>From:</strong>
                        <p>
                            @if($salesOrder->creator)
                                {{ $salesOrder->creator->name }}<br>
                                {{ $salesOrder->creator->email }}
                            @endif
                        </p>
                    </td>
                    <td class="header-cell">
                        <table style="width: 100%;">
                            <tr>
                                <td class="text-right"></td>
                                <td class="text-right" style="padding-left: 10px; color: #333;">Number:
                                    {{ $salesOrder->order_number }}
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td class="text-right" style="padding-left: 10px; color: #333;">
                                    Order Date:
                                    {{ $salesOrder->order_date ? \Carbon\Carbon::parse($salesOrder->order_date)->format('M d, Y') : '-' }}<br>
                                    Delivery Date:
                                    {{ $salesOrder->delivery_date ? \Carbon\Carbon::parse($salesOrder->delivery_date)->format('M d, Y') : '-' }}
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
                            @if($salesOrder->account) {{ $salesOrder->account->name }}<br> @endif
                            @if($salesOrder->billingContact)
                                {{ $salesOrder->billingContact->name }}<br>
                                {{ $salesOrder->billingContact->email }}<br>
                                {{ $salesOrder->billingContact->phone }}<br>
                            @endif
                            {{ $salesOrder->billing_address }}<br>
                            {{ $salesOrder->billing_city }} {{ $salesOrder->billing_state }}
                            {{ $salesOrder->billing_country }}
                        </p>
                    </td>
                    <td class="text-right">
                        <strong>Ship To:</strong>
                        <p>
                            @if($salesOrder->shippingContact)
                                {{ $salesOrder->shippingContact->name }}<br>
                                {{ $salesOrder->shippingContact->email }}<br>
                                {{ $salesOrder->shippingContact->phone }}<br>
                            @endif
                            {{ $salesOrder->shipping_address }}<br>
                            {{ $salesOrder->shipping_city }} {{ $salesOrder->shipping_state }}
                            {{ $salesOrder->shipping_country }}
                        </p>
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
                    @foreach($salesOrder->products as $product)
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
                        </table>
                    </td>
                </tr>
            </table>

        </div>
    </div>
</body>

</html>