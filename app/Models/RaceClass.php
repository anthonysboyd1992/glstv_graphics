<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $short_name
 * @property string|null $role_key
 * @property int $sort_order
 */
#[Fillable(['name', 'short_name', 'role_key', 'sort_order'])]
class RaceClass extends Model
{
    public function displayName(): string
    {
        return $this->short_name ?: $this->name;
    }
}
