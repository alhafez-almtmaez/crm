<?php

namespace App\Exceptions;

class WhatsAppRecipientNotRegisteredException extends WhatsAppMessageSendException
{
    // A known, permanent recipient failure: no send attempt has occurred.
}
