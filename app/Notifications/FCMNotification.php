<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as ResourcesNotification;

class FCMNotification extends Notification
{
    use Notifiable, Queueable;

    public function __construct(protected string $title, protected string $body, protected int $type = FCM_TYPE_NORMAL) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFCM(object $notifiable): FcmMessage
    {
        $fcm = new FcmMessage(notification: new ResourcesNotification(
            title: $this->title,
            body: $this->body,
            image: 'https://portal.anypos.app/logo.png')
        );

        if ($this->type === FCM_TYPE_RELOAD) {
            $fcm = $fcm->data(['type' => 'RELOAD']);
        }
        return $fcm;
    }
}
