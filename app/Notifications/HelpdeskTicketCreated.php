<?php

namespace App\Notifications;

use App\Mail\MailHelpdeskTicketCreated;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

class HelpdeskTicketCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public HelpdeskTicket $helpdeskTicket)
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
        return (new MailHelpdeskTicketCreated($this->helpdeskTicket))
            ->to($notifiable->email);
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'helpdesk_ticket_id' => $this->helpdeskTicket->id,
            'helpdesk_ticket_description' => $this->helpdeskTicket->description,
            'helpdesk_ticket_created_by' => $this->helpdeskTicket->customer?->company?->name,
            'helpdesk_ticket_status' => $this->helpdeskTicket->status,
            'notification' => 'HelpdeskTicketCreated',
            'message' => 'Helpdesk ticket is created',
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'helpdesk_ticket_id' => $this->helpdeskTicket->id,
            'helpdesk_ticket_description' => $this->helpdeskTicket->description,
            'helpdesk_ticket_created_by' => $this->helpdeskTicket->customer?->company?->name,
            'helpdesk_ticket_status' => $this->helpdeskTicket->status,
            'notification' => 'HelpdeskTicketCreated',
            'message' => 'Helpdesk ticket is created',
            'read_at' => null,
        ]);
    }
}
