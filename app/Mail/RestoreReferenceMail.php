<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RestoreReferenceMail extends Mailable
{
    use Queueable, SerializesModels;
    public $ref;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($token_string)
    {
        $this->ref = $token_string;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('reference_mail',[$this->ref])->subject('Ссылка на изменение настроек');
    }
}
