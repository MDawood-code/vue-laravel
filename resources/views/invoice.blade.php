<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice Slip</title>
    <style>
        @font-face {
            font-family: "OpenSans-Regular";
            src: url("/fonts/OpenSans-Regular.ttf") format("truetype");
        }

        @font-face {
            font-family: "OpenSans-SemiBold";
            src: url("/fonts/OpenSans-SemiBold.ttf") format("truetype");
        }

        *, body {
            padding: 0;
            margin: 0;
            font-family: 'OpenSans-Regular';
        }

        .valign-top {
            vertical-align: baseline;
        }

        .semi-bold {
            font-family: "OpenSans-SemiBold";
        }

        #container {
            width: 800px;
            margin: 10px auto;
            border: 1px solid;
            padding: 20px;
        }

        table.bordered {
            border-spacing: 0;
        }

        table.bordered th {
            font-family: "OpenSans-SemiBold";
        }

        table.bordered td {
            padding: 2px 4px;
            border: 1px solid #c9c9c9;
        }

        table.bordered tr :not(td:first-child) {
            border-left: none;
        }

        table.bordered :not(tr:last-child td) {
            border-bottom: none;
        }

        table.bordered tr td.no-border {
            border-bottom: none;
            border-left: none;
            border-right: none;
        }

        table.bordered td {
        }

        .align-left {
            text-align: left;
        }

        .align-center {
            text-align: center;
        }

        .align-right {
            text-align: right;
        }
    </style>
</head>
<body>
<div id="container">
    <h2 style="font-family: 'OpenSans-SemiBold';text-align: center;text-decoration: underline">
        Invoice: {{ $invoice->uid }}
    </h2>
    <br/><br/>

    <div style="display: flex; justify-content: space-between; align-items: start">
        <div>
            <h3 style="font-family: 'OpenSans-SemiBold';text-decoration: underline">Company Details</h3>
            <table>
                <tr>
                    <td>Company</td>
                    <td>:</td>
                    <td class="semi-bold">{{ $company->name }}</td>
                </tr>
                <tr>
                    <td>Business Type</td>
                    <td>:</td>
                    <td class="semi-bold">{{ $company->business_type }}</td>
                </tr>
                <tr>
                    <td>CR #</td>
                    <td>:</td>
                    <td class="semi-bold">{{ $company->cr }}</td>
                </tr>
                <tr>
                    <td>VAT #</td>
                    <td>:</td>
                    <td class="semi-bold">{{ $company->vat }}</td>
                </tr>
                <tr>
                    <td>Date</td>
                    <td>:</td>
                    <td class="semi-bold">{{ Carbon\Carbon::parse($invoice->created_at)->setTimezone('Asia/Riyadh')->format('d/m/Y H:i:s') }}</td>
                </tr>
            </table>
        </div>
        <img src="{{ $company->logo }}" style="max-width: 200px;max-height: 200px"/>
    </div>


    <br/>

    <h3 style="font-family: 'OpenSans-SemiBold';text-decoration: underline">Purchase Details</h3>
    <table style="width: 100%" class="bordered">
        <tr>
            <th>Item</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>SubTotal</th>
        </tr>
        @php
            $total_amount = 0;
        @endphp
        @foreach ($invoice_details as $detail)
            @if (in_array($detail->type, [INVOICE_DETAIL_TYPE_SUBSCRIPTION, INVOICE_DETAIL_TYPE_LICENSE, INVOICE_DETAIL_TYPE_DEVICE_PAYMENT, INVOICE_DETAIL_TYPE_ADDON, INVOICE_DETAIL_TYPE_BALANCE_TOPUP]))
                <tr>
                    <td>{{ $detail->item }}</td>
                    <td class="align-center">{{ $detail->quantity }}</td>
                    <td class="align-right">{{ number_format($detail->amount /$detail->quantity, 2).' SAR' }}</td>
                    <td class="align-right">{{ number_format($detail->amount, 2).' SAR' }}</td>
                </tr>
                @php
                    $total_amount += $detail->amount;
                @endphp
            @endif
        @endforeach
        <tr>
            <td colspan="3" class="align-right semi-bold">Total</td>
            <td class="align-right semi-bold">{{ number_format($total_amount, 2).' SAR' }}</td>
        </tr>
        <tr>
            <td colspan="4" class="no-border" style="height: 20px;"></td>
        </tr>
        @php
            $amount_before_discount = $total_amount;
        @endphp
        @foreach ($invoice_details as $detail)
            @if ($detail->type !== INVOICE_DETAIL_TYPE_DISCOUNT) @continue @endif
            <tr>
                <td colspan="3" class="align-right">{{ $detail->item }}</td>
                <td class="align-right ">{{ number_format($detail->amount, 2).' SAR' }}</td>
            </tr>
            @php
                $total_amount += $detail->amount;
            @endphp
        @endforeach
        @if ($amount_before_discount > $total_amount)
            <tr>
                <td colspan="3" class="align-right semi-bold">Total after Discount</td>
                <td class="align-right semi-bold">{{ number_format($total_amount, 2).' SAR' }}</td>
            </tr>
            <tr>
                <td colspan="4" class="no-border" style="height: 20px;"></td>
            </tr>
        @endif
        @foreach ($invoice_details as $detail)
            @if ($detail->type !== INVOICE_DETAIL_TYPE_TAX) @continue @endif
            <tr>
                <td colspan="3" class="align-right">{{ $detail->item }}</td>
                <td class="align-right">{{ number_format($detail->amount, 2).' SAR' }}</td>
            </tr>
            @php
                $total_amount += $detail->amount;
            @endphp
        @endforeach
        <tr>
            <td colspan="3" class="align-right semi-bold">Amount Charged</td>
            <td class="align-right semi-bold">{{ number_format($total_amount, 2).' SAR' }}</td>
        </tr>
    </table>
    <br/>
    <br/>
    <h3 class="semi-bold">Status:
        @if ($invoice->status === INVOICE_STATUS_UNPAID )
            <span style="color:#999">Unpaid</span>
        @elseif ($invoice->status === INVOICE_STATUS_CANCELLED )
            <span style="color:#f00">Cancelled</span>
        @elseif ($invoice->status === INVOICE_STATUS_PAID )
            <span style="color:#0f0">Paid</span>
        @elseif ($invoice->status === INVOICE_STATUS_REFUNDED )
            <span style="color:#f00">Refunded</span>
        @endif
    </h3>
    <br/>
    <br/>
    <small>It's a system generated invoice, if you find any issue, kindly contact at support@anypos.app</small>
</div>
</body>
</html>
