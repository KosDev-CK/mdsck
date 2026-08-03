<?php

namespace App\Livewire\Connections;

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Manage extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $key = '';

    public string $module = '';

    public string $driver = 'mysql';

    public string $mode = 'single';

    public string $host = '';

    public ?int $port = null;

    public string $database = '';

    public string $username = '';

    public string $password = '';

    public string $baseUrl = '';

    public string $extraJson = '';

    public ?int $poolMin = null;

    public ?int $poolMax = null;

    public bool $isActive = true;

    public bool $showForm = false;

    public ?string $testResult = null;

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id)
    {
        $connection = DatabaseConnection::findOrFail($id);

        $this->editingId = $connection->id;
        $this->name = $connection->name;
        $this->key = $connection->key;
        $this->module = (string) $connection->module;
        $this->driver = $connection->driver;
        $this->mode = $connection->mode;
        $this->host = (string) $connection->host;
        $this->port = $connection->port;
        $this->database = (string) $connection->database;
        $this->username = (string) $connection->username;
        $this->password = ''; // never re-populate a stored secret into the form
        $this->baseUrl = (string) $connection->base_url;
        $this->extraJson = $connection->extra ? json_encode($connection->extra, JSON_PRETTY_PRINT) : '';
        $this->poolMin = $connection->pool_min;
        $this->poolMax = $connection->pool_max;
        $this->isActive = $connection->is_active;
        $this->testResult = null;
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'key', 'module', 'host', 'port', 'database',
            'username', 'password', 'baseUrl', 'extraJson', 'poolMin', 'poolMax', 'testResult',
        ]);
        $this->driver = 'mysql';
        $this->mode = 'single';
        $this->isActive = true;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'alpha_dash', 'max:100', 'unique:database_connections,key,'.$this->editingId],
            'module' => ['nullable', 'string', 'max:255'],
            'driver' => ['required', 'in:mysql,pgsql,sqlsrv,api'],
            'mode' => ['required', 'in:pool,single'],
            'host' => ['required_unless:driver,api', 'nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer'],
            'database' => ['required_unless:driver,api', 'nullable', 'string', 'max:255'],
            'baseUrl' => ['required_if:driver,api', 'nullable', 'url', 'max:255'],
            'extraJson' => ['nullable', 'json'],
            'poolMin' => ['nullable', 'integer', 'min:0'],
            'poolMax' => ['nullable', 'integer', 'gte:poolMin'],
        ];
    }

    protected function buildAttributes(): array
    {
        $connection = $this->editingId ? DatabaseConnection::find($this->editingId) : null;

        return [
            'name' => $this->name,
            'key' => $this->key,
            'module' => $this->module ?: null,
            'driver' => $this->driver,
            'mode' => $this->mode,
            'host' => $this->driver === 'api' ? null : $this->host,
            'port' => $this->driver === 'api' ? null : $this->port,
            'database' => $this->driver === 'api' ? null : $this->database,
            'username' => $this->username ?: null,
            'password' => $this->password !== '' ? $this->password : $connection?->password,
            'base_url' => $this->driver === 'api' ? $this->baseUrl : null,
            'extra' => $this->extraJson !== '' ? json_decode($this->extraJson, true) : null,
            'pool_min' => $this->mode === 'pool' ? $this->poolMin : null,
            'pool_max' => $this->mode === 'pool' ? $this->poolMax : null,
            'is_active' => $this->isActive,
        ];
    }

    public function save()
    {
        $this->validate();

        $attributes = $this->buildAttributes();

        if ($this->editingId) {
            DatabaseConnection::find($this->editingId)->update($attributes);
        } else {
            DatabaseConnection::create($attributes + ['created_by' => auth()->id()]);
        }

        $this->showForm = false;
        session()->flash('status', 'Conexión guardada.');
    }

    public function delete(int $id)
    {
        DatabaseConnection::find($id)?->delete();
    }

    public function testConnection()
    {
        $this->validate();

        $attributes = $this->buildAttributes();

        if ($this->driver === 'api') {
            $this->testResult = $this->testApiConnection($attributes);

            return;
        }

        $this->testResult = $this->testDatabaseConnection($attributes);
    }

    protected function testDatabaseConnection(array $attributes): string
    {
        $connectionName = 'connection_test_'.uniqid();

        config(["database.connections.{$connectionName}" => [
            'driver' => $attributes['driver'],
            'host' => $attributes['host'],
            'port' => $attributes['port'],
            'database' => $attributes['database'],
            'username' => $attributes['username'],
            'password' => $attributes['password'],
            'charset' => 'utf8mb4',
        ]]);

        try {
            DB::connection($connectionName)->getPdo();

            return 'ok:Conexión exitosa.';
        } catch (\Throwable $e) {
            return 'error:No se pudo conectar ('.$e->getMessage().').';
        } finally {
            DB::purge($connectionName);
        }
    }

    protected function testApiConnection(array $attributes): string
    {
        try {
            $response = Http::timeout(5)->get($attributes['base_url']);

            return $response->successful()
                ? 'ok:La API respondió correctamente ('.$response->status().').'
                : 'error:La API respondió con estado '.$response->status().'.';
        } catch (\Throwable $e) {
            return 'error:No se pudo contactar la API ('.$e->getMessage().').';
        }
    }

    public function render()
    {
        return view('livewire.connections.manage', [
            'connections' => DatabaseConnection::orderBy('name')->get(),
        ]);
    }
}
