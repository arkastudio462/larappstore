<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('credit_ledger')]
#[Fillable(['user_id', 'delta', 'type', 'balance_after', 'order_id', 'description'])]
class CreditLedger extends Model
{
    public const TYPE_SIGNUP_BONUS = 'signup_bonus';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_USAGE = 'usage';

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
