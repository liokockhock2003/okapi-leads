<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadStatusCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Lead $lead)
    {
    }

    public function envelope(): Envelope
    {
        // Deliberately warm and status-neutral — a disqualified lead must not
        // read as a rejection from the subject line alone.
        return new Envelope(
            subject: 'Thanks for your interest in going solar ☀️',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lead-status-customer',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
