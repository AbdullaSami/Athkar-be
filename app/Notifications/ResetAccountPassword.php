<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** Deliver a broker-generated reset link through the configured credential-safe mailer. */
class ResetAccountPassword extends ResetPassword
{
    /** Build the message without using the application's potentially logging default mailer. */
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = config('auth.password_reset_url').'?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], encoding_type: PHP_QUERY_RFC3986);

        return $this->buildMailMessage($url)->mailer(config('auth.password_reset_mailer'));
    }
}
