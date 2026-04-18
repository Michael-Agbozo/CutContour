<?php

namespace App\Notifications;

use App\Models\CutJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class CutJobNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $status  'completed' | 'failed'
     */
    public function __construct(
        public readonly CutJob $cutJob,
        public readonly string $status,
    ) {}

    /**
     * Completed jobs go to both database and mail; failures are in-app only.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->status === 'completed'
            ? ['database', 'mail']
            : ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'cut_job_id' => $this->cutJob->id,
            'original_name' => $this->cutJob->original_name,
            'status' => $this->status,
            'download_url' => $this->status === 'completed' ? $this->signedDownloadUrl() : null,
            'error_message' => $this->status === 'failed' ? $this->cutJob->error_message : null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your file is ready: {$this->cutJob->original_name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your CutContour file **{$this->cutJob->original_name}** has been processed successfully.")
            ->action('Download PDF', $this->signedDownloadUrl())
            ->line('This download link expires in **7 days**.')
            ->line('Thank you for using CutContour!');
    }

    private function signedDownloadUrl(): string
    {
        return URL::temporarySignedRoute(
            'jobs.download',
            now()->addDays(7),
            ['cutJob' => $this->cutJob->id],
        );
    }
}
