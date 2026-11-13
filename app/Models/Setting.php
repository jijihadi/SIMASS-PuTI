<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const SEASONAL_EFFECTS = ['snow', 'leaves', 'rain'];

    /**
     * Efek musiman yang aktif: null = mati.
     * Mode 'auto' (default) ikut bulan; selain itu dipaksa admin.
     */
    public static function seasonalEffect(?CarbonInterface $now = null): ?string
    {
        $mode = static::where('key', 'seasonal_effect')->value('value') ?? 'auto';

        if ($mode !== 'auto') {
            return in_array($mode, self::SEASONAL_EFFECTS, true) ? $mode : null;
        }

        return match (($now ?? now())->month) {
            12 => 'snow',
            9, 10 => 'leaves',
            11, 1, 2, 3 => 'rain',
            default => null,
        };
    }
}
