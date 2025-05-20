<x-mail::message>
# Odoo Company Error

Company with the following details could not be created on Odoo.
<x-mail::table>
| Company ID         | Company Name       | User Name     |
| ------------------ |:------------------:| -------------:|
| {{ $company->id }}   | {{ $company->name }} | {{ $company->owner->name }}   |
</x-mail::table>

@if ($error_message)
<x-mail::panel>
{{ $error_message }}
</x-mail::panel>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
