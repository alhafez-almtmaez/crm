<?php

namespace App\Services\Admin\AbsenceRules;

class MessageDispatchResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $sent,
        public readonly array $meta = [],
    ) {}

    public static function notSent(): self
    {
        return new self(false);
    }

    public static function sent(): self
    {
        return new self(true);
    }

    /**
     * @param  array<int, string>  $recipientPhones
     */
    public static function localPreview(array $recipientPhones, ?string $groupSerialized): self
    {
        return new self(false, [
            'local_preview' => true,
            'whatsapp_skipped' => true,
            'skip_reason' => 'local_environment',
            'recipient_phones' => array_values($recipientPhones),
            'group_serialized' => $groupSerialized,
        ]);
    }
}
