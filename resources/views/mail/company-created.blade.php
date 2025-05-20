<x-mail::message>
# Company Created

Company with the following details is created.
<x-mail::table>
| Company ID         | User Name       | Contact#     |
| ------------------ |:------------------:| -------------:|
| {{ $company->id }}   | {{ $company->owner->name }} | {{ $company->owner->phone }}   |
</x-mail::table>


Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
