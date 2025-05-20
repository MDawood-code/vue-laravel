<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Transaction Slip</title>
    <style>
        @font-face {
            font-family: "Amiri";
            src: url("/fonts/Amiri-Regular.ttf") format("truetype");
        }

        @font-face {
            font-family: "Amiri-Bold";
            src: url("/fonts/Amiri-Bold.ttf") format("truetype");
        }

        @font-face {
            font-family: "OpenSans-Regular";
            src: url("/fonts/OpenSans-Regular.ttf") format("truetype");
        }

        @font-face {
            font-family: "OpenSans-SemiBold";
            src: url("/fonts/OpenSans-SemiBold.ttf") format("truetype");
        }

        *,
        body {
            font-size: 12px;
            padding: 0;
            margin: 0;
            font-family: 'OpenSans-Regular';
        }

        span {
            font-family: 'Amiri-Bold';
        }

        h3,
        h3>span {
            font-size: 16px;
        }

        .valign-top {
            vertical-align: baseline;
        }

        /*@media screen {*/
        #slip {
            width: 320px;
            border: 1px solid;
            margin: 10px auto;
            padding: 10px;
        }

        /*}*/
    </style>
</head>

<body>
    <div style="background-color: #fff; padding: 20px;">
        <div id="slip" style="max-width: 550px; margin: 0 auto; width: 100%; padding: 10px; border: 1px solid #000;">
            <div style="text-align: center; margin-top: 10px; padding-left: 10px;">
                <img id="store-logo" style="max-height: 120px; max-width: 200px; margin: 10px auto 20px;"
                    src="{{ $company->logo }}" alt="{{ $company->business_name }}'s Logo">
                <br>
            </div>
            <h3 style="font-size: 16px; text-align: center; border-bottom: 1px solid #4e4e4e;">
                @if ($transaction->reference_transaction)
                    <span style="font-size: 16px;">فاتورة الاسترجاع</span><br>Refund Invoice
                @else
                    <span style="font-size: 16px;">فاتورة ضریبیة المبسطة</span><br>Simplified Tax Invoice
                @endif
            </h3>
            <div style="width: 100%; margin-top: 5px;">
                <table cellspacing="0" cellpadding="0" style="width: 100%; font-size: 12px;">
                    <tbody>
                        <tr key="col-width-1">
                            <td style="width: 120px;"></td>
                            <td></td>
                        </tr>
                        <tr key="invoice_no_row">
                            <td style="font-size: 12px;">
                                @if ($transaction->reference_transaction)
                                    <span style="font-size: 12px;">رقم فاتورة الاسترداد :</span><br>Refund Invoice
                                    No
                                @else
                                    <span style="font-size: 12px;">رقم الفاتورة :</span><br>Invoice No
                                @endif
                            </td>
                            <td style="font-size: 12px;" class="valign-top">{{ $transaction->uid }}</td>
                        </tr>
                        <tr key="issue_date_row">
                            <td style="font-size: 12px; width: 200px;">
                                <span style="font-size: 12px;">تاريخ الاصدار :</span><br>Issue Date
                            </td>
                            <td style="font-size: 12px;" class="valign-top" id="transaction-datetime">
                                {{ \Carbon\Carbon::parse($transaction->created_at)->setTimezone('Asia/Riyadh')->format('d/m/Y H:i:s') }}
                            </td>
                        </tr>
                        @if ($company->is_vat_exempt)
                            <tr key="exempt_vat_row">
                                <td style="font-size: 12px;">
                                    <span style="font-size: 12px;">ضريبة القيمة المضافة المعفاة :</span><br>Exempt
                                    VAT
                                </td>
                                <td style="font-size: 12px;" class="valign-top">Yes</td>
                            </tr>
                        @else
                            <tr key="vat_no_row">
                                <td style="font-size: 12px;">
                                    <span style="font-size: 12px;">الرقم الضريبي :</span><br>VAT No
                                </td>
                                <td style="font-size: 12px;" class="valign-top">{{ $company->vat }}</td>
                            </tr>
                        @endif
                        @if ($transaction->buyer_company_name)
                            <tr key="vat_no_row">
                                <td style="font-size: 12px;">
                                    <span style="font-size: 12px;">اسم شركة المشتري</span><br>Buyer Company Name
                                </td>
                                <td style="font-size: 12px;" class="valign-top">{{ $transaction->buyer_company_name }}
                                </td>
                            </tr>
                        @endif
                        @if ($transaction->buyer_company_vat)
                            <tr key="vat_no_row">
                                <td style="font-size: 12px;">
                                    <span style="font-size: 12px;">رقم ضريبة القيمة المضافة لشركة
                                        المشتري</span><br>Buyer Company VAT No.
                                </td>
                                <td style="font-size: 12px;" class="valign-top">{{ $transaction->buyer_company_vat }}
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                <table cellspacing="0" cellpadding="0" style="width: 100%; font-size: 12px;">
                    <tbody>
                        <tr key="col-width-2">
                            <td style="width: 75px;"></td>
                            <td></td>
                            <td style="width: 60px;"></td>
                            <td style="width: 100px;"></td>
                        </tr>
                        <tr key="spacing_1_row">
                            <td colspan="4" style="border-bottom: 1px solid #4e4e4e; height: 10px;"></td>
                        </tr>
                        <tr key="products_header" class="products-header">
                            <td style="font-size: 12px; text-align: left; borderBottom: 1px solid #4e4e4e;"
                                colspan="2">الصنف<br>Item</td>
                            <td style="font-size: 12px; text-align: right; borderBottom: 1px solid #4e4e4e;">سعر
                                الوحدة<br>U.P</td>
                            <td style="font-size: 12px; text-align: right; borderBottom: 1px solid #4e4e4e;">السعر
                                مع الضريبة<br>P.VAT</td>
                        </tr>
                        @foreach ($transaction->items as $item)
                            <tr key="product_items_{{ $item->id }}" class="product-items">
                                <td style="font-size: 12px; text-align: left;" colspan="2" class="valign-top">
                                    {{ $item->name }} x {{ $item->quantity }}<br>{{ $item->name_en }}</td>
                                <td style="font-size: 12px; text-align: right;" class="valign-top">
                                    {{ $item->price }}</td>
                                <td style="font-size: 12px; text-align: right;" class="valign-top">
                                    {{ $item->subtotal }}</td>
                            </tr>
                        @endforeach
                        <tr key="spacing_2_row">
                            <td colspan="4" style="height: 10px; borderTop: 1px solid #4e4e4e"></td>
                        </tr>
                        @if ($transaction->discount && !$transaction->reference_transaction)
                            <tr key="subtotal_row">
                                <td colspan="3" style="font-size: 12px;">المجموع الفرعي<br>Subtotal (Excluding
                                    VAT)</td>
                                <td style="font-size: 12px; text-align: right;" id="subtotal-amount-without-tax">
                                    {{ $transaction->amount_charged - $transaction->tax + $transaction->discount_amount }}
                                </td>
                            </tr>
                            <tr key="discount_row">
                                <td colspan="3" style="font-size: 12px;">تخفيض<br>Discount
                                    {{ $transaction->discount?->discount_percentage }}%</td>
                                <td style="font-size: 12px; text-align: right;" id="discount">-
                                    {{ $transaction->discount_amount }}</td>
                            </tr>
                        @endif
                        <tr key="total_taxable_row">
                            <td colspan="3" style="font-size: 12px;">الإجمالي الخاضع للضریبة<br>Total Taxable
                                (Excluding VAT)</td>
                            <td style="font-size: 12px; text-align: right;" id="total-amount-without-tax">
                                {{ $transaction->amount_charged - $transaction->tax }}</td>
                        </tr>
                        <tr key="total_vat_row">
                            <td colspan="3" style="font-size: 12px;">مجموع ضریبة القیمة المضافة<br>Total VAT</td>
                            <td style="font-size: 12px; text-align: right;" id="total-tax">{{ $transaction->tax }}</td>
                        </tr>
                        <tr key="spacing_3row">
                            <td colspan="4" style="height: 10px; borderTop: 1px solid #4e4e4e"></td>
                        </tr>
                        <tr key="total_amount_row">
                            <td colspan="2" style="font-size: 16px;">إجمالي المبلغ المستحق<br>Total Amount</td>
                            <td style="text-align: right; font-size: 16px;" colspan="2" id="total-amount">
                                {{ $transaction->amount_charged }}</td>
                        </tr>
                        <tr key="spacing_4_row">
                            <td colspan="4" style="height: 20px;"></td>
                        </tr>
                    </tbody>
                </table>
                <div style="text-align: center;">
                    <img src="{{ $qr_code }}" alt="QR Code" style="width: 140px; margin: 10px auto;" />
                    <br>
                    <small>Printed by {{ config('app.url') }}</small>
                    <br>
                </div>
            </div>
        </div>
    </div>



    {{-- <div id="slip">
    <div style="text-align:center; margin-top:10px; padding-left:10px;">
        <img id="store-logo" style="max-height: 120px; max-width:200px; margin:10px auto 20px;"
             src="{{ asset($company->logo) }}"
             alt="{{ $company->name }}'s Logo"/><br/>
    </div>
    <h3 style="font-family: 'OpenSans-SemiBold';text-align: center;border-bottom:1px solid #4e4e4e;">
        <span style="font-family: 'Amiri-Bold';">فاتورة ضریبیة المبسطة</span><br/>
        Simplified Tax Invoice
    </h3>

    <div style="width:100%;margin-top:5px;">
        <table cellspacing="0" cellpadding="0" style="width:100%;">
            <tbody>
            <tr>
                <td style="width:75px;"></td>
                <td></td>
                <td style="width:60px;"></td>
                <td style="width:85px;"></td>
            </tr>
            <tr>
                <td>
                    <span>رقم الفاتورة :</span><br/>
                    Invoice No
                </td>
                <td colspan="3" class="valign-top">{{ $transaction->uid }}</td>
            </tr>
            <tr>
                <td>
                    <span>تاريخ الاصدار :</span><br/>
                    Issue Date
                </td>
                <td colspan="3" class="valign-top" id="transaction-datetime">
                    {{ \Carbon\Carbon::parse($transaction->created_at)->setTimezone('Asia/Riyadh')->format('d/m/Y H:i:s') }}
                </td>
            </tr>
            <tr>
                <td>
                    <span>الرقم الضريبي :</span><br/>
                    VAT No
                </td>
                <td colspan="3" class="valign-top">{{ $company->vat }}</td>
            </tr>
            @if (!empty($transaction->buyer_company_name))
                <tr>
                    <td>
                        <span>اسم شركة المشتري</span><br/>
                        Buyer Company Name
                    </td>
                    <td colspan="3" class="valign-top">{{ $transaction->buyer_company_name }}</td>
                </tr>
            @endif
            @if (!empty($transaction->buyer_company_vat))
                <tr>
                    <td>
                        <span>رقم ضريبة القيمة المضافة لشركة المشتري</span><br/>
                        Buyer Company VAT No
                    </td>
                    <td colspan="3" class="valign-top">{{ $transaction->buyer_company_vat }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="4" style="border-bottom:1px solid #4e4e4e;height: 10px"></td>
            </tr>
            <tr class="products-header">
                <td style="text-align:left;border-bottom:1px solid #4e4e4e;" colspan="2">
                    <span>الصنف</span><br/>
                    Item
                </td>
                <td style="text-align:right;border-bottom:1px solid #4e4e4e;">
                    <span>سعر الوحدة</span><br/>
                    U.P
                </td>
                <td style="text-align:right;border-bottom:1px solid #4e4e4e;">
                    <span>السعر مع الضريبة</span><br/>
                    P.VAT

                </td>
            </tr>
            @foreach ($transaction->items as $key => $item)
                <tr class="product-items">
                    <td style="text-align:left;" colspan="2" class="valign-top">
                        <span>{{ $item->name }}</span> x {{ $item->quantity }}<br/>
                        {{ $item->name_en }}
                    </td>
                    <td style="text-align:right;" class="valign-top">{{ $item->price }}</td>
                    <td style="text-align:right;"
                        class="valign-top">{{ $item->subtotal }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4" style="height: 20px;border-top:1px solid #4e4e4e;"></td>
            </tr>
            <tr>
                <td colspan="3">
                    <span>الإجمالي الخاضع للضریبة</span><br/>
                    Total Taxable (Excluding VAT)
                </td>
                <td style=" text-align:right;" id="total-amount-without-tax">
                    {{ 'SAR ' . ($transaction->amount_charged - $transaction->tax) }}
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span>مجموع ضریبة القیمة المضافة</span><br/>
                    Total VAT
                </td>
                <td style=" text-align:right;" id="total-tax">
                    {{ 'SAR ' . $transaction->tax }}
                </td>
            </tr>
            <tr>
                <td colspan="4" style="height: 10px;border-top:1px solid #4e4e4e;"></td>
            </tr>
            <tr>
                <td colspan="2" style="font-size: 16px;">
                    <span style="font-size: 16px;">إجمالي المبلغ المستحق</span><br/>
                    Total Amount
                </td>
                <td style=" text-align:right;font-size: 16px;" colspan="2" id="total-amount">
                    {{ 'SAR ' . $transaction->amount_charged }}
                </td>
            </tr>
            <tr>
                <td colspan="4" style="height: 20px"></td>
            </tr>
            </tbody>
        </table>
        <div style="text-align: center">
            <img src="{{ $qr_code }}" alt="QR Code"
                 style="width: 140px; margin: 10px auto;"/><br>
            <small>{{ "Printed by https://anypos.app" }}</small>
            <br>
        </div>
    </div>
</div> --}}
</body>

</html>
