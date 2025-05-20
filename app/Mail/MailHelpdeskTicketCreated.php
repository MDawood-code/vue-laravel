<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;
use App\Models\HelpdeskTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailHelpdeskTicketCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public HelpdeskTicket $helpdeskTicket)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Helpdesk Ticket Created',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.helpdesk-ticket-created',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
