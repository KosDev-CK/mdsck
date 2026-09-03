<?php

namespace Modules\GestionTI\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * "Fusionar duplicados" — compartido por las pantallas de catálogos
 * config-driven (Nucleo/Compras/Inventario) que declaran una clave
 * `mergeReferences` en su `catalogos()` para la pestaña actual. Cada entrada
 * de `mergeReferences` es `['model' => FQCN, 'column' => 'fk_column']` — toda
 * otra tabla del módulo que tiene una FK apuntando a este catálogo (ver el
 * mapeo completo documentado en docs/gestionti-progreso.md).
 *
 * Requiere que la clase anfitriona tenga: `public string $tab`, un método
 * `catalogos(): array` con la config por pestaña (incluyendo `model`,
 * `label`, `orderBy` y, opcionalmente, `mergeReferences`).
 */
trait MergesCatalogDuplicates
{
    public bool $showMergeModal = false;

    public ?int $mergeDeleteId = null;

    public ?int $mergeKeepId = null;

    public function openMerge(): void
    {
        $this->mergeDeleteId = null;
        $this->mergeKeepId = null;
        $this->resetValidation();
        $this->showMergeModal = true;
    }

    public function cancelMerge(): void
    {
        $this->showMergeModal = false;
        $this->mergeDeleteId = null;
        $this->mergeKeepId = null;
        $this->resetValidation();
    }

    /**
     * Fusiona dos registros del catálogo activo: repunta toda referencia
     * configurada del registro "a eliminar" hacia el "que se conserva" y
     * BORRA (no desactiva) el duplicado — "que quede solo 1 registro" es
     * literal, desactivar no lo cumple.
     */
    public function confirmMerge(): void
    {
        $config = $this->catalogos()[$this->tab];
        $table = (new $config['model'])->getTable();

        $this->validate([
            'mergeDeleteId' => ['required', 'integer', 'different:mergeKeepId', "exists:{$table},id"],
            'mergeKeepId' => ['required', 'integer', "exists:{$table},id"],
        ], [
            'mergeDeleteId.different' => 'Selecciona dos registros distintos para fusionar.',
        ]);

        $deleteId = (int) $this->mergeDeleteId;
        $keepId = (int) $this->mergeKeepId;

        $deleteLabel = $this->mergeOptionLabel($config['model']::findOrFail($deleteId));
        $keepLabel = $this->mergeOptionLabel($config['model']::findOrFail($keepId));

        $reassigned = 0;

        DB::transaction(function () use ($config, $deleteId, $keepId, &$reassigned) {
            foreach ($config['mergeReferences'] ?? [] as $reference) {
                $reassigned += $reference['model']::where($reference['column'], $deleteId)
                    ->update([$reference['column'] => $keepId]);
            }

            $config['model']::findOrFail($deleteId)->delete();
        });

        $this->showMergeModal = false;
        $this->mergeDeleteId = null;
        $this->mergeKeepId = null;

        session()->flash('status', "Fusión completa: se eliminó \"{$deleteLabel}\" y se conservó \"{$keepLabel}\". Se repuntaron {$reassigned} referencias.");
    }

    /**
     * Etiqueta legible de un registro para los selects de fusión y el
     * `wire:confirm` — reutiliza el mismo criterio "nombre visible" de cada
     * catálogo (nombre comercial, código+nombre, o nombre simple).
     */
    public function mergeOptionLabel($record): string
    {
        if (! $record) {
            return '';
        }

        if (isset($record->nombre_comercial)) {
            return (string) $record->nombre_comercial;
        }

        if (isset($record->codigo) && isset($record->nombre)) {
            return "{$record->codigo} — {$record->nombre}";
        }

        if (isset($record->codigo) && isset($record->descripcion)) {
            return "{$record->codigo} — {$record->descripcion}";
        }

        return (string) ($record->nombre ?? $record->codigo ?? "#{$record->id}");
    }
}
