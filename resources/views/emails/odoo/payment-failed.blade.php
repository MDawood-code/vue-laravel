<x-mail::message>
# Odoo Payment Error

Payment with the following details could not be created on Odoo.
<x-mail::table>
| Payment ID         | Amount             | Brand         | Invoice ID    | Company Name   |
| ------------------ |:------------------:| -------------:| -------------:| -------------:|
| {{ $payment->id }}   | {{ $payment->amount }} | {{ $payment->brand }}   | {{ $payment->invoice_id }}   | {{ $payment->company?->name }} |
</x-mail::table>

@if ($error_message)
<x-mail::panel>
{{ $error_message }}
</x-mail::panel>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
