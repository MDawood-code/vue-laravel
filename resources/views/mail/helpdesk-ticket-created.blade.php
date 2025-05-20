<x-mail::message>
# Helpdesk Ticket Created

Helpdesk ticket with the following details is created.
<x-mail::table>
| Ticket ID          | Company            | Contact#     | CSR          |
| ------------------ |:------------------:|:------------:|:------------:|
| {{ $helpdeskTicket->id }}   | {{ $helpdeskTicket->customer?->company?->name }} | {{ $helpdeskTicket->customer?->company?->owner?->phone }}   | {{ $helpdeskTicket->supportAgent?->name }}     |
</x-mail::table>


Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
