<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class CustomVerifyEmailNotification extends BaseVerifyEmail
{
    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifikasi Pos-el - Koze')
            ->greeting('Halo ' . $notifiable->name . '!')
            ->line('Terima kasih telah bergabung di Koze. Silakan klik tombol di bawah ini untuk memverifikasi pos-el Anda dan menyelesaikan pendaftaran.')
            ->action('Verifikasi Email', $verificationUrl)
            ->line('Jika Anda tidak merasa membuat akun di aplikasi kami, Anda dapat mengabaikan pesan ini. Email Anda akan tetap aman.')
            ->salutation('Salam hangat, Tim Koze');
    }
}
