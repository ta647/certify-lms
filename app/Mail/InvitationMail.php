<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invitation;
use App\Notifications\Concerns\RetriesTemporaryFailures;
use App\Services\InvitationTokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * キュー化(T-A-05): 招待メール送信で発火元リクエストをブロックしないよう worker に送信を委ねる。
 */
class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, RetriesTemporaryFailures, SerializesModels;

    public function __construct(public Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->invitation->email],
            subject: 'Certify LMS への招待',
        );
    }

    public function content(): Content
    {
        $url = app(InvitationTokenService::class)->generateUrl($this->invitation);

        return new Content(
            markdown: 'emails.invitation',
            with: [
                'invitation' => $this->invitation,
                'invitedBy' => $this->invitation->invitedBy,
                'roleLabel' => $this->invitation->role->label(),
                'expiresAt' => $this->invitation->expires_at,
                'url' => $url,
            ],
        );
    }
}
