<?php

namespace App\Services\Admin;

use App\Models\MessageTemplate;
use App\Services\System\DateTimeFormatterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MessageTemplateService
{
    public function __construct(private readonly DateTimeFormatterService $dateTimeFormatter)
    {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');

        $sortMap = [
            'id' => 'message_templates.id',
            'key' => 'message_templates.key',
            'name' => 'message_templates.name',
            'locale' => 'message_templates.locale',
            'is_active' => 'message_templates.is_active',
            'created_at' => 'message_templates.created_at',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'message_templates.id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $query = MessageTemplate::query()
            ->select([
                'message_templates.id',
                'message_templates.key',
                'message_templates.name',
                'message_templates.locale',
                'message_templates.content',
                'message_templates.placeholders',
                'message_templates.is_active',
                'message_templates.created_at',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('message_templates.name', 'like', "%{$search}%")
                        ->orWhere('message_templates.key', 'like', "%{$search}%")
                        ->orWhere('message_templates.content', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $sortDir);

        $templates = $query->paginate($perPage)->withQueryString();
        $templates->setCollection(
            $templates->getCollection()->map(function ($row) {
                $row->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($row->created_at));

                return $row;
            }),
        );

        return $templates;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): MessageTemplate
    {
        return MessageTemplate::query()->create($this->payload($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(MessageTemplate $template, array $data): MessageTemplate
    {
        $template->update($this->payload($data));

        return $template->refresh();
    }

    public function delete(MessageTemplate $template): void
    {
        $template->delete();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return [
            'key' => $data['key'],
            'name' => $data['name'],
            'locale' => $data['locale'] ?? 'ar',
            'content' => $data['content'],
            'placeholders' => $data['placeholders'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
