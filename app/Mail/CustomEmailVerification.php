<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $verificationLink;

    /**
     * Create a new message instance.
     */
    public function __construct(public $rawToken)
    {
        // Generate the frontend verification link
        $frontendUrl = config('app.frontend_url'); // Get from .env
        $this->verificationLink = $frontendUrl . '/verify-email?token=' . urlencode($this->rawToken);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Custom Email Verification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.custom_verification',
            with: ['verificationLink' => $this->verificationLink],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
