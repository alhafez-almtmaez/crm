<?php

namespace App\Services\Admin;

use App\Exceptions\WhatsAppMessageSendException;
use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\WhatsAppPendingMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $sourceType = is_string($sourceType) ? trim($sourceType) : null;
        $sourceId = is_numeric($sourceId) ? (int) $sourceId : null;

        if ($chatIds === [] || ! $this->isSupportedSource($sourceType, $sourceId)) {
            return null;
        }

        return WhatsAppPendingMessage::query()->create([
            'chat_ids' => $chatIds,
            'content' => $content,
            'media_url' => $mediaUrl,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => WhatsAppPendingMessage::STATUS_PENDING,
            'last_error' => $lastError,
            'available_at' => now(),
        ]);
    }

    /**
     * @return array{checked: int, sent: int, failed: int, stale: int}
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
            'stale' => 0,
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

            $resolved = $this->resolveForDispatch($message);
            if (isset($resolved['stale_reason'])) {
                $this->markStale($message, $resolved['stale_reason']);
                $summary['stale']++;

                continue;
            }

            $message->update([
                'chat_ids' => $resolved['chat_ids'],
                'content' => $resolved['content'],
                'media_url' => $resolved['media_url'],
            ]);

            try {
                $this->messagingService->sendMediaCaptionToChatIds(
                    $resolved['chat_ids'],
                    $resolved['content'],
                    $resolved['media_url'],
                    queueOnFailure: false,
                );

            } catch (WhatsAppMessageSendException $exception) {
                $remainingChatIds = $this->normalizeChatIds($exception->unsentChatIds());

                if ($message->source_type === WhatsAppPendingMessage::SOURCE_ABSENCE_RULE_EXECUTION_LOG
                    && $remainingChatIds !== []
                    && count($remainingChatIds) < count($resolved['chat_ids'])) {
                    $message->update(['chat_ids' => $remainingChatIds]);
                    $this->markStale($message, 'partial_delivery_requires_review');

                    try {
                        $this->markSourceAsPartiallySent(
                            $message,
                            $resolved,
                            $remainingChatIds,
                            $exception->getMessage(),
                        );
                    } catch (Throwable $syncException) {
                        Log::error('Partially sent WhatsApp pending message could not synchronize its source.', [
                            'pending_message_id' => $message->id,
                            'source_type' => $message->source_type,
                            'source_id' => $message->source_id,
                            'error' => $syncException->getMessage(),
                        ]);
                    }

                    $summary['stale']++;

                    continue;
                }

                $message->update([
                    'chat_ids' => $remainingChatIds === [] ? $message->chat_ids : $remainingChatIds,
                    'status' => WhatsAppPendingMessage::STATUS_PENDING,
                    'last_error' => $exception->getMessage(),
                    'available_at' => now()->addMinute(),
                ]);

                $summary['failed']++;

                continue;
            } catch (Throwable $exception) {
                $message->update([
                    'status' => WhatsAppPendingMessage::STATUS_PENDING,
                    'last_error' => $exception->getMessage(),
                    'available_at' => now()->addMinute(),
                ]);

                $summary['failed']++;

                continue;
            }

            $sentAt = now();
            $message->update([
                'status' => WhatsAppPendingMessage::STATUS_SENT,
                'sent_at' => $sentAt,
                'last_error' => null,
            ]);

            try {
                $this->markSourceAsSent($message, $resolved, $sentAt);
            } catch (Throwable $exception) {
                // Delivery has already happened. Never make this row retryable,
                // even if synchronizing its source unexpectedly fails.
                $message->update([
                    'last_error' => 'sent_but_source_sync_failed: '.$exception->getMessage(),
                ]);

                Log::error('WhatsApp pending message was sent but its source could not be synchronized.', [
                    'pending_message_id' => $message->id,
                    'source_type' => $message->source_type,
                    'source_id' => $message->source_id,
                    'error' => $exception->getMessage(),
                ]);
            }

            $summary['sent']++;
        }

        return $summary;
    }

    /**
     * Retire queued copies before a user performs a manual resend. A processing
     * copy cannot be retired safely, so the caller must wait rather than risk a
     * duplicate delivery.
     */
    public function retirePendingForSource(string $sourceType, int $sourceId): bool
    {
        return DB::transaction(function () use ($sourceType, $sourceId): bool {
            $messages = WhatsAppPendingMessage::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->whereIn('status', [
                    WhatsAppPendingMessage::STATUS_PENDING,
                    WhatsAppPendingMessage::STATUS_PROCESSING,
                    WhatsAppPendingMessage::STATUS_SENT,
                    WhatsAppPendingMessage::STATUS_STALE,
                ])
                ->lockForUpdate()
                ->get(['id', 'status', 'last_error']);

            if ($messages->contains('status', WhatsAppPendingMessage::STATUS_PROCESSING)) {
                return false;
            }

            if ($messages->contains('status', WhatsAppPendingMessage::STATUS_SENT)) {
                return false;
            }

            if ($messages->contains(static fn (WhatsAppPendingMessage $message): bool => $message->status === WhatsAppPendingMessage::STATUS_STALE
                && str_contains((string) $message->last_error, 'partial_delivery_requires_review'))) {
                return false;
            }

            $pendingIds = $messages
                ->where('status', WhatsAppPendingMessage::STATUS_PENDING)
                ->pluck('id');

            WhatsAppPendingMessage::query()
                ->whereKey($pendingIds)
                ->update([
                    'status' => WhatsAppPendingMessage::STATUS_STALE,
                    'available_at' => null,
                    'last_error' => 'stale: superseded_by_manual_resend',
                ]);

            return true;
        });
    }

    /**
     * @return array{chat_ids: array<int, string>, content: string, media_url: string|null, recipient_phones?: array<int, string>, group_serialized?: string|null}|array{stale_reason: string}
     */
    private function resolveForDispatch(WhatsAppPendingMessage $message): array
    {
        if ($message->source_type === WhatsAppPendingMessage::SOURCE_DIRECT) {
            $chatIds = $this->normalizeChatIds($message->chat_ids ?? []);
            $content = trim((string) $message->content);

            if ($chatIds === [] || $content === '') {
                return ['stale_reason' => 'direct_message_incomplete'];
            }

            return [
                'chat_ids' => $chatIds,
                'content' => $content,
                'media_url' => $message->media_url,
            ];
        }

        if ($message->source_type !== WhatsAppPendingMessage::SOURCE_ABSENCE_RULE_EXECUTION_LOG
            || $message->source_id === null) {
            return ['stale_reason' => 'unverifiable_source'];
        }

        $log = AbsenceRuleExecutionLog::query()
            ->with([
                'evaluation.group:id,center_id,group_serialized',
                'student:id,parent_phone_number,phone_number',
                'rule:id,action,deduction_points_count',
                'freezeRecord:id',
            ])
            ->find($message->source_id);

        if (! $log || ! $log->evaluation || ! $log->evaluation->group || ! $log->student) {
            return ['stale_reason' => 'source_not_resolvable'];
        }

        if ($log->was_message_sent) {
            return ['stale_reason' => 'source_already_sent'];
        }

        $error = $log->meta['error'] ?? null;
        if (! is_string($error) || trim($error) === '') {
            return ['stale_reason' => 'source_is_not_failed'];
        }

        if (! $this->canDispatchAbsenceLog($log)) {
            return ['stale_reason' => 'business_action_not_applied'];
        }

        $content = trim((string) $log->message_content);
        $groupSerialized = $log->sent_to_group
            ? $log->evaluation->group->group_serialized
            : null;
        $recipientPhones = array_values(array_filter([
            $log->student->parent_phone_number,
            $log->student->phone_number,
        ], static fn (mixed $phone): bool => is_string($phone) && trim($phone) !== ''));
        $chatIds = $this->messagingService->recipientChatIds(
            $recipientPhones,
            is_string($groupSerialized) ? $groupSerialized : null,
        );

        if ($content === '' || $chatIds === []) {
            return ['stale_reason' => 'source_message_incomplete'];
        }

        return [
            'chat_ids' => $chatIds,
            'content' => $content,
            'media_url' => $message->media_url,
            'recipient_phones' => $recipientPhones,
            'group_serialized' => is_string($groupSerialized) ? $groupSerialized : null,
        ];
    }

    /**
     * @param  array{recipient_phones?: array<int, string>, group_serialized?: string|null}  $resolved
     */
    private function markSourceAsSent(WhatsAppPendingMessage $message, array $resolved, Carbon $sentAt): void
    {
        if ($message->source_type !== WhatsAppPendingMessage::SOURCE_ABSENCE_RULE_EXECUTION_LOG
            || $message->source_id === null) {
            return;
        }

        DB::transaction(function () use ($message, $resolved, $sentAt): void {
            $log = AbsenceRuleExecutionLog::query()
                ->with('evaluation.group:id,center_id')
                ->lockForUpdate()
                ->find($message->source_id);

            if (! $log || $log->was_message_sent) {
                return;
            }

            $meta = $log->meta ?? [];
            $meta['error'] = null;
            $meta['pending_message_id'] = (int) $message->id;
            $meta['pending_sent_at'] = $sentAt->toIso8601String();
            $meta['pending_source'] = 'verified_absence_rule_execution_log';
            $meta['group_serialized'] = $resolved['group_serialized'] ?? null;

            $log->update([
                'center_id' => $log->evaluation?->group?->center_id ?? $log->center_id,
                'recipient_phones' => $resolved['recipient_phones'] ?? [],
                'was_message_sent' => true,
                'executed_at' => $sentAt,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * @param  array{chat_ids: array<int, string>, recipient_phones?: array<int, string>, group_serialized?: string|null}  $resolved
     * @param  array<int, string>  $remainingChatIds
     */
    private function markSourceAsPartiallySent(
        WhatsAppPendingMessage $message,
        array $resolved,
        array $remainingChatIds,
        string $error,
    ): void {
        if ($message->source_id === null) {
            return;
        }

        DB::transaction(function () use ($message, $resolved, $remainingChatIds, $error): void {
            $log = AbsenceRuleExecutionLog::query()
                ->lockForUpdate()
                ->find($message->source_id);

            if (! $log || $log->was_message_sent) {
                return;
            }

            $meta = $log->meta ?? [];
            $meta['error'] = $error;
            $meta['pending_message_id'] = (int) $message->id;
            $meta['pending_partial_delivery'] = true;
            $meta['pending_partial_at'] = now()->toIso8601String();
            $meta['pending_delivered_chat_ids'] = array_values(array_diff(
                $resolved['chat_ids'],
                $remainingChatIds,
            ));
            $meta['pending_remaining_chat_ids'] = $remainingChatIds;
            $meta['group_serialized'] = $resolved['group_serialized'] ?? null;

            $log->update([
                'recipient_phones' => $resolved['recipient_phones'] ?? [],
                'executed_at' => now(),
                'meta' => $meta,
            ]);
        });
    }

    public function canDispatchAbsenceLog(AbsenceRuleExecutionLog $log): bool
    {
        $log->loadMissing(['rule:id,action,deduction_points_count', 'freezeRecord:id']);

        return $this->businessActionWasApplied($log);
    }

    private function businessActionWasApplied(AbsenceRuleExecutionLog $log): bool
    {
        if (! $log->rule || (int) $log->deduction_points_count !== (int) $log->rule->deduction_points_count) {
            return false;
        }

        return match ($log->action) {
            AbsenceRule::ACTION_SEND_MESSAGE => true,
            AbsenceRule::ACTION_FREEZE_STUDENT,
            AbsenceRule::ACTION_SEND_MESSAGE_AND_FREEZE => $log->student_was_frozen && $log->freezeRecord !== null,
            AbsenceRule::ACTION_DISMISS_STUDENT => $log->student_was_dismissed,
            default => false,
        };
    }

    private function markStale(WhatsAppPendingMessage $message, string $reason): void
    {
        $message->update([
            'status' => WhatsAppPendingMessage::STATUS_STALE,
            'available_at' => null,
            'last_error' => "stale: {$reason}",
        ]);
    }

    private function isSupportedSource(?string $sourceType, ?int $sourceId): bool
    {
        return match ($sourceType) {
            WhatsAppPendingMessage::SOURCE_DIRECT => $sourceId === null,
            WhatsAppPendingMessage::SOURCE_ABSENCE_RULE_EXECUTION_LOG => $sourceId !== null && $sourceId > 0,
            default => false,
        };
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
