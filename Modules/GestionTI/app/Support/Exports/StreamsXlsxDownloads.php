<?php

namespace Modules\GestionTI\Support\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Helper compartido por los controllers de exportación "Exportar a Excel" de
 * las 4 pantallas de catálogos — construye y transmite un .xlsx de una sola
 * hoja a partir de encabezados + filas ya resueltas (mismo patrón de
 * controller+ruta que `Modules\FormBuilder\Http\Controllers\TicketFormLinkPdfController`
 * para descargas de archivo, en vez de intentarlo desde un método Livewire).
 *
 * Todas las celdas se escriben con `setCellValueExplicit(...,
 * DataType::TYPE_STRING)` en vez de `fromArray()` — el mismo bug ya
 * documentado en `ImportarHistoricoCommand` aplica aquí: cualquier valor de
 * catálogo que por casualidad empiece con "=" sería interpretado como
 * fórmula por el comportamiento por defecto de PhpSpreadsheet.
 */
trait StreamsXlsxDownloads
{
    protected function streamXlsx(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $this->writeRow($sheet, 1, $headers);

        $rowIndex = 2;
        foreach ($rows as $row) {
            $this->writeRow($sheet, $rowIndex, $row);
            $rowIndex++;
        }

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        $writer = new XlsxWriter($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function writeRow($sheet, int $rowIndex, iterable $values): void
    {
        $columnIndex = 1;

        foreach ($values as $value) {
            $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$rowIndex;
            $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
            $columnIndex++;
        }
    }
}
