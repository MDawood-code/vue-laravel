<x-mail::message>
# Helpdesk Ticket {{ $status }}

Helpdesk ticket with the following details is {{ $status }} since {{ $since }}.
<x-mail::table>
| Ticket ID          | Company            | Contact#     | CSR          |
| ------------------ |:------------------:|:------------:|:------------:|
| {{ $helpdeskTicket->id }}   | {{ $helpdeskTicket->customer?->company?->name }} | {{ $helpdeskTicket->customer?->company?->owner?->phone }}   | {{ $helpdeskTicket->supportAgent?->name }}     |
</x-mail::table>


Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
