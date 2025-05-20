<?php

namespace App\Notifications;

use App\Mail\DelayedTicketMail;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

class HelpdeskTicketDelayed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public HelpdeskTicket $helpdeskTicket, public string $status, public string $since)
    {
        $this->helpdeskTicket->load('customer.company.owner');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return App::environment('production') ? ['mail', 'database', 'broadcast'] : ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): Mailable
    {
        return (new DelayedTicketMail($this->helpdeskTicket, $this->status, $this->since))
            ->to($notifiable->email);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'helpdesk_ticket_id' => $this->helpdeskTicket->id,
            'helpdesk_ticket_description' => $this->helpdeskTicket->description,
            'helpdesk_ticket_created_by' => $this->helpdeskTicket->customer?->company?->name,
            'helpdesk_ticket_status' => $this->helpdeskTicket->status,
            'notification' => $this->status === 'Late' ? 'HelpdeskTicketLate' : 'HelpdeskTicketDelayed',
            'message' => "Helpdesk Ticket is {$this->status} since {$this->since}",
        ];
    }
}
