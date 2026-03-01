<?php

namespace App\Mail\Referral;

use App\Models\DiscountCode;
use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferralRewardEarned extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Referral $referral,
        public DiscountCode $discountCode
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sie haben einen Empfehlungsbonus erhalten!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.referral.reward-earned',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
