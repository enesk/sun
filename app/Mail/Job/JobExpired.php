<?php

namespace App\Mail\Job;

use App\Models\Portal\Company;
use App\Models\Portal\Job;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobExpired extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Job $job,
        public Company $company,
        public Tenant $tenant,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihre Stellenanzeige „' . $this->job->title . '" ist abgelaufen',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.job.expired',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
