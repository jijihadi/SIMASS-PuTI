<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryStatusLookup extends Model
{
    use HasFactory;

    protected $table = 'inventory_statuses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'color',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function label(): string
    {
        return $this->name;
    }

    public function getValueAttribute(): string
    {
        return $this->code;
    }
}
