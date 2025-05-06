<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(public User $user, public string $token)
    {
        // ✅ Construct the reset URL (Frontend link)
        $this->resetUrl = config('app.frontend_url') . "/reset-password?token={$this->token}&email={$this->user->email}";
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Reset Your Password')
            ->view('emails.password_reset')
            ->with(['resetUrl' => $this->resetUrl]);
    }
}

