<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'locale',
        'content',
        'placeholders',
        'is_active',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'bool',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(AbsenceRule::class, 'message_template_id');
    }
}
