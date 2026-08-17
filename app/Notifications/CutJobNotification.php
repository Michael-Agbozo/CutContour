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

    /**
     * DB payload never stores a signed download URL. A prior version stored
     * one with a 15-minute TTL; that both leaked information (a leaked
     * notifications table gave anyone a session-independent download link)
     * AND broke the in-app flow (any user opening the notifications page
     * more than 15 min after the job finished got a 403). Consumers must
     * build a fresh URL from `cut_job_id` at render time — see
     * resources/views/components/⚡notification-bell.blade.php and
     * resources/views/pages/notifications/_row.blade.php.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'cut_job_id' => $this->cutJob->id,
            'original_name' => $this->cutJob->original_name,
            'status' => $this->status,
            'error_message' => $this->status === 'failed' ? $this->cutJob->error_message : null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your file is ready: {$this->cutJob->original_name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your CutContour file **{$this->cutJob->original_name}** has been processed successfully.")
            ->action('Download PDF', $this->signedDownloadUrl(now()->addDays(7)))
            ->line('This download link expires in **7 days**.')
            ->line('Thank you for using CutContour!');
    }

    private function signedDownloadUrl(\DateTimeInterface $expiration): string
    {
        return URL::temporarySignedRoute(
            'jobs.download',
            $expiration,
            ['cutJob' => $this->cutJob->id],
        );
    }
}
