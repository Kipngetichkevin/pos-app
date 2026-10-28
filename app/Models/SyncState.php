<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Key/value store for the branch server's sync cursors and last-run status. */
class SyncState extends Model
{
    protected $table = 'sync_states';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public static function get(string $key, $default = null)
    {
        return static::find($key)?->value ?? $default;
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    // Aliases used by some callers in the sync client
    public static function read(string $key, $default = null)
    {
        return static::get($key, $default);
    }

    public static function write(string $key, $value): void
    {
        static::put($key, $value);
    }
}