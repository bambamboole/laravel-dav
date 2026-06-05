<?php

namespace Bambamboole\LaravelDav\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Default iMIP (RFC 6047) scheduling email; override to customise. The iCalendar
 * payload is attached as a `text/calendar` part carrying the iTip method.
 */
class SchedulingMessageMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $fromEmail,
        public string $replyToEmail,
        public string $recipientEmail,
        public string $subjectLine,
        public string $summary,
        public string $method,
        public string $calendarBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail),
            replyTo: [new Address($this->replyToEmail)],
            to: [new Address($this->recipientEmail)],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e($this->summary).'</p>');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->calendarBody, 'invite.ics')
                ->withMime('text/calendar; charset=UTF-8; method='.$this->method),
        ];
    }
}
