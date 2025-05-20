<x-mail::message>
# Company Assigned

Company with the following details is assigned to you.
<x-mail::table>
| Company ID         | User Name       | Contact#     |
| ------------------ |:------------------:| -------------:|
| {{ $company->id }}   | {{ $company->owner->name }} | {{ $company->owner->phone }}   |
</x-mail::table>


Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
