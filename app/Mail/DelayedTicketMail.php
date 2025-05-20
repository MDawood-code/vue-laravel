<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;
use App\Models\HelpdeskTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DelayedTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public HelpdeskTicket $helpdeskTicket, public string $status, public string $since)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->status.' Ticket',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.delayed-ticket-mail',
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
