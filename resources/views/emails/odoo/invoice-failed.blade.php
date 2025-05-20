<x-mail::message>
# Odoo Invoice Error

Invoice with the following details could not be created on Odoo.
<x-mail::table>
| Invoice ID         | Amount             | Company Name     |
| ------------------ |:------------------:| -------------:|
| {{ $invoice->id }}   | {{ $invoice->amount_charged }} | {{ $invoice->company->name }}   |
</x-mail::table>

@if ($error_message)
<x-mail::panel>
{{ $error_message }}
</x-mail::panel>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
