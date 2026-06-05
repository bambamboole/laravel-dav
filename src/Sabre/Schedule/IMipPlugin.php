<?php

namespace Bambamboole\LaravelDav\Sabre\Schedule;

use Bambamboole\LaravelDav\Mail\SchedulingMessageMail;
use Illuminate\Support\Facades\Mail;
use Sabre\CalDAV\Schedule\IMipPlugin as BaseIMipPlugin;
use Sabre\VObject\ITip\Message;

/**
 * Sends iMIP (RFC 6047) email for scheduling messages bound for non-local
 * attendees, using Laravel's mailer.
 */
class IMipPlugin extends BaseIMipPlugin
{
    public function schedule(Message $iTipMessage): void
    {
        if (! $iTipMessage->significantChange) {
            if (! $iTipMessage->scheduleStatus) {
                $iTipMessage->scheduleStatus = '1.0;We got the message, but it is not significant enough to warrant an email';
            }

            return;
        }

        // Already delivered to a local inbox by sabre; emailing would double-deliver.
        if (str_starts_with((string) $iTipMessage->scheduleStatus, '1.')) {
            return;
        }

        if (parse_url((string) $iTipMessage->sender, PHP_URL_SCHEME) !== 'mailto'
            || parse_url((string) $iTipMessage->recipient, PHP_URL_SCHEME) !== 'mailto') {
            return;
        }
        Mail::mailer(config('dav.scheduling.mailer'))->send($this->buildMessage($iTipMessage));

        $iTipMessage->scheduleStatus = '1.1;Scheduling message is sent via iMip';
    }

    private function buildMessage(Message $iTipMessage): SchedulingMessageMail
    {
        $method = strtoupper((string) $iTipMessage->method);
        $summary = (string) ($iTipMessage->message->VEVENT->SUMMARY ?? 'Calendar invitation');

        return new SchedulingMessageMail(
            fromEmail: $this->senderEmail,
            replyToEmail: mb_substr((string) $iTipMessage->sender, 7),
            recipientEmail: mb_substr((string) $iTipMessage->recipient, 7),
            subjectLine: $this->subjectFor($method, $summary),
            summary: $summary,
            method: $method,
            calendarBody: $iTipMessage->message->serialize(),
        );
    }

    private function subjectFor(string $method, string $summary): string
    {
        return match ($method) {
            'REQUEST' => 'Invitation: '.$summary,
            'REPLY' => 'Re: '.$summary,
            'CANCEL' => 'Cancelled: '.$summary,
            default => $summary,
        };
    }
}
