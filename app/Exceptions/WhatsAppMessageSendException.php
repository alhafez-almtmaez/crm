<?php

namespace App\Exceptions;

use RuntimeException;

class WhatsAppMessageSendException extends RuntimeException
{
    /**
     * @param  array<int, string>  $unsentChatIds
     */
    public function __construct(string $message, private readonly array $unsentChatIds)
    {
        parent::__construct($message);
    }

    /**
     * @return array<int, string>
     */
    public function unsentChatIds(): array
    {
        return $this->unsentChatIds;
    }
}
