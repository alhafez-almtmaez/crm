<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Evaluation;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    protected $model = Evaluation::class;

    public function configure(): static
    {
        return $this->afterMaking(static function (Evaluation $evaluation): void {
            if ($evaluation->group_id === null) {
                return;
            }

            $centerId = Group::query()->whereKey($evaluation->group_id)->value('center_id');
            if ($centerId !== null) {
                $evaluation->center_id = (int) $centerId;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'center_id' => Center::factory(),
            'group_id' => static function (array $attributes): int {
                $centerId = (int) $attributes['center_id'];
                $existingGroupId = Group::query()
                    ->where('center_id', $centerId)
                    ->orderBy('id')
                    ->value('id');

                return $existingGroupId !== null
                    ? (int) $existingGroupId
                    : (int) Group::factory()->create(['center_id' => $centerId])->id;
            },
            'admin_id' => User::factory(),
            'is_send_absence_alerts' => fake()->boolean(35),
            'evaluation_type' => fake()->randomElement([Evaluation::TYPE_ALHIFZ, Evaluation::TYPE_TAJWID]),
        ];
    }
}
