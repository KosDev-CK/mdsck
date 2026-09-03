<?php

namespace Modules\GestionTI\Console\Commands\Support;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Limita la lectura del archivo a un rango de filas.
 *
 * El Excel histórico (`Inventario_20210622.xlsx`) reporta un "highestRow" de
 * más de un millón de filas por formato residual en celdas muy por debajo de
 * los datos reales (~3800 filas útiles) — sin este filtro, PhpSpreadsheet
 * agota la memoria por defecto (128M) intentando materializar objetos de
 * celda vacíos hasta esa fila fantasma. Ver docs/gestionti-progreso.md,
 * sección Fase 2 / Excel histórico.
 */
class RowRangeReadFilter implements IReadFilter
{
    public function __construct(private readonly int $maxRow)
    {
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $row <= $this->maxRow;
    }
}
