<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Who did what sensitive thing, when. Rows are only ever added, never edited. */
class AuditLog extends Model
{
    use HasUuids;

    public const ACTIONS = [
        'price.changed' => 'Price / cost changed',
        'credit_limit.changed' => 'Credit limit changed',
        'user.changed' => 'User role or access changed',
        'sale.voided' => 'Sale voided',
        'sale.returned' => 'Items returned / refunded',
        'sale.below_cost' => 'Sold below cost',
        'mpesa.unverified' => 'Unverified M-Pesa accepted',
        'stock.adjusted' => 'Stock adjusted',
        'expense.removed' => 'Expense removed',
        'shift.closed' => 'Shift closed with a difference',
    ];

    protected $table = 'audit_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public static function record(string $action, string $description, ?Model $subject = null, ?array $changes = null): self
    {
        $user = auth()->user();

        return static::create([
            'branch_id' => $user?->branch_id ?? ($subject?->branch_id ?? null),
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 255),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
        ]);
    }

    /** Old/new pairs for the given attributes of a model that was just updated. */
    public static function diff(Model $model, array $attributes): array
    {
        $changes = [];

        foreach ($attributes as $attribute) {
            if ($model->wasChanged($attribute)) {
                $changes[$attribute] = [$model->getOriginal($attribute), $model->getAttribute($attribute)];
            }
        }

        return $changes;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
