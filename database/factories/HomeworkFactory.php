<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Group;
use App\Models\Homework;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Homework>
 */
class HomeworkFactory extends Factory
{
    protected $model = Homework::class;

    public function configure(): static
    {
        return $this->afterMaking(static function (Homework $homework): void {
            if ($homework->group_id === null) {
                return;
            }

            $centerId = Group::query()->whereKey($homework->group_id)->value('center_id');
            if ($centerId !== null) {
                $homework->center_id = (int) $centerId;
            }
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'center_id' => Center::factory(),
            'group_id' => static function (array $attributes): int {
                $centerId = (int) $attributes['center_id'];
                $groupId = Group::query()
                    ->where('center_id', $centerId)
                    ->orderBy('id')
                    ->value('id');

                return $groupId !== null
                    ? (int) $groupId
                    : (int) Group::factory()->create(['center_id' => $centerId])->id;
            },
            'admin_id' => User::factory(),
        ];
    }
}
