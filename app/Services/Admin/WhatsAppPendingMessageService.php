<?php

namespace App\Services\Admin;

use App\Exceptions\WhatsAppMessageSendException;
use App\Models\WhatsAppPendingMessage;
use Throwable;

class WhatsAppPendingMessageService
{
    public function __construct(private readonly WhatsAppMessagingService $messagingService) {}

    /**
     * @param  array<int, string>  $chatIds
     */
    public function enqueue(
        array $chatIds,
        string $content,
        ?string $mediaUrl = null,
        ?string $sourceType = null,
        int|string|null $sourceId = null,
        ?string $lastError = null,
    ): ?WhatsAppPendingMessage {
        $chatIds = $this->normalizeChatIds($chatIds);

        if ($chatIds === []) {
            return null;
        }

        return WhatsAppPendingMessage::query()->create([
            'chat_ids' => $chatIds,
            'content' => $content,
            'media_url' => $mediaUrl,
            'source_type' => $sourceType,
            'source_id' => is_numeric($sourceId) ? (int) $sourceId : null,
            'status' => WhatsAppPendingMessage::STATUS_PENDING,
            'last_error' => $lastError,
            'available_at' => now(),
        ]);
    }

    /**
     * @return array{checked: int, sent: int, failed: int}
     */
    public function flushPending(int $limit = 20): array
    {
        $limit = max(1, $limit);
        $messages = WhatsAppPendingMessage::query()
            ->readyToSend()
            ->oldest('id')
            ->limit($limit)
            ->get();

        $summary = [
            'checked' => $messages->count(),
            'sent' => 0,
            'failed' => 0,
        ];

        foreach ($messages as $message) {
            $claimed = WhatsAppPendingMessage::query()
                ->whereKey($message->id)
                ->where('status', WhatsAppPendingMessage::STATUS_PENDING)
                ->update([
                    'status' => WhatsAppPendingMessage::STATUS_PROCESSING,
                    'attempts' => $message->attempts + 1,
                    'last_attempted_at' => now(),
                    'last_error' => null,
                ]);

            if ($claimed === 0) {
                continue;
            }

            $message->refresh();

            try {
                $this->messagingService->sendMediaCaptionToChatIds(
                    $message->chat_ids ?? [],
                    $message->content,
                    $message->media_url,
                    queueOnFailure: false,
                );

                $message->update([
                    'status' => WhatsAppPendingMessage::STATUS_SENT,
                    'sent_at' => now(),
                    'last_error' => null,
                ]);

                $summary['sent']++;
            } catch (WhatsAppMessageSendException $exception) {
                $remainingChatIds = $this->normalizeChatIds($exception->unsentChatIds());

                $message->update([
                    'chat_ids' => $remainingChatIds === [] ? $message->chat_ids : $remainingChatIds,
                    'status' => WhatsAppPendingMessage::STATUS_PENDING,
                    'last_error' => $exception->getMessage(),
                    'available_at' => now()->addMinute(),
                ]);

                $summary['failed']++;
            } catch (Throwable $exception) {
                $message->update([
                    'status' => WhatsAppPendingMessage::STATUS_PENDING,
                    'last_error' => $exception->getMessage(),
                    'available_at' => now()->addMinute(),
                ]);

                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * @param  array<int, string>  $chatIds
     * @return array<int, string>
     */
    private function normalizeChatIds(array $chatIds): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $chatId): string => trim((string) $chatId),
            $chatIds,
        ))));
    }
}
