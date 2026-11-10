<?php

namespace SubKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $top_up_id
 * @property string $provider
 * @property string $provider_price_id
 */
class TopUpProviderPrice extends Model
{
    protected $table = 'subkit_top_up_provider_prices';

    protected $fillable = [
        'top_up_id',
        'provider',
        'provider_price_id',
    ];

    public function topUp(): BelongsTo
    {
        return $this->belongsTo(TopUp::class);
    }
}
