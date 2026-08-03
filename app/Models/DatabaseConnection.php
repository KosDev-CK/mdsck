<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseConnection extends Model
{
    protected $fillable = [
        'name',
        'key',
        'module',
        'driver',
        'mode',
        'host',
        'port',
        'database',
        'username',
        'password',
        'base_url',
        'extra',
        'pool_min',
        'pool_max',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'username' => 'encrypted',
            'password' => 'encrypted',
            'extra' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Build a Laravel database connection config array so this connection
     * can be registered at runtime via config(["database.connections.{$this->key}" => ...]).
     */
    public function toConnectionConfig(): array
    {
        return array_filter([
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ], fn ($value) => ! is_null($value));
    }

    /**
     * Build the HTTP client options for connections of type "api"
     * (base_url + headers/tokens stored in the encrypted `extra` payload).
     */
    public function toApiConfig(): array
    {
        return [
            'base_url' => $this->base_url,
            'options' => $this->extra ?? [],
        ];
    }
}
