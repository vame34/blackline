<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Lang;

class SignalCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected function nameLocalized(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function (): string {
                $key = 'content.signal_categories.'.$this->slug.'.name';

                return Lang::has($key) ? __($key) : $this->name;
            },
        );
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }
}
