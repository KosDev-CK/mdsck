<?php

namespace Modules\GestionTI\Http\Controllers\PresupuestoProyectos;

use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * "Exportar a Excel" de Presupuesto por Proyecto — reconstruye la estructura
 * real del Excel corporativo que se usa para conseguir la firma (compartido
 * por el usuario 2026-09-04, hoja "Presuppuesto" de
 * "Presupuesto Koscon Epsilonre.xlsx", ver docs/gestionti-progreso.md):
 * artículos agrupados en 5 categorías contables fijas con subtotal cada una
 * (One Time / On going / Total), un subtotal general, y un bloque final con
 * el `factor_administrativo` del proyecto aplicado.
 *
 * No usa `Modules\GestionTI\Support\Exports\StreamsXlsxDownloads` (ese trait
 * solo sirve para una tabla plana de encabezado+filas, como los exports de
 * catálogos) — este documento tiene secciones, subtotales y un bloque de
 * totales con fórmulas, así que construye el `Spreadsheet` directamente.
 * Mantiene el mismo criterio de seguridad de esa clase: cualquier texto de
 * catálogo (descripción, proveedor, etc.) se escribe con
 * `setCellValueExplicit(..., DataType::TYPE_STRING)`, nunca con
 * `setCellValue()` a secas, para que un valor que por casualidad empiece con
 * "=" nunca se interprete como fórmula.
 *
 * No replica el membrete de marca (logo/colores) ni el catálogo de precios
 * externo (`XLOOKUP` contra otra hoja en el original) — el objetivo es el
 * contenido/estructura del documento firmado, no un clon visual del archivo
 * fuente.
 */
class ExportController extends Controller
{
    private const MONEY_FORMAT = '#,##0.00';

    public function __invoke(ProyectoPresupuesto $proyectoPresupuesto)
    {
        $proyectoPresupuesto->load('articulos.responsableCosto');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Presupuesto');

        $row = $this->writeTitulo($sheet, $proyectoPresupuesto);
        $row = $this->writeEncabezados($sheet, $row);

        $porCategoria = $proyectoPresupuesto->articulos->groupBy(
            fn (ProyectoPresupuestoArticulo $articulo) => $articulo->categoria_contable ?? '__sin_categoria__'
        );

        $categorias = ProyectoPresupuestoArticulo::CATEGORIAS_CONTABLES;
        if ($porCategoria->has('__sin_categoria__')) {
            $categorias[] = '__sin_categoria__';
        }

        $totalesGenerales = ['one_time' => 0.0, 'on_going' => 0.0, 'total' => 0.0];
        $numero = 1;

        foreach ($categorias as $categoriaContable) {
            $articulos = $porCategoria->get($categoriaContable, collect());

            $label = $categoriaContable === '__sin_categoria__'
                ? 'Sin categoría contable'
                : (ProyectoPresupuestoArticulo::CATEGORIA_CONTABLE_LABELS[$categoriaContable] ?? $categoriaContable);

            $row = $this->writeCategoriaHeader($sheet, $row, "{$numero}. ".mb_strtoupper($label));
            $numero++;

            $subtotal = ['one_time' => 0.0, 'on_going' => 0.0, 'total' => 0.0];

            foreach ($articulos as $articulo) {
                $montos = $this->montosDe($articulo);
                $subtotal['one_time'] += $montos['one_time'];
                $subtotal['on_going'] += $montos['on_going'];
                $subtotal['total'] += $montos['total'];

                $row = $this->writeArticuloRow($sheet, $row, $articulo, $montos);
            }

            $row = $this->writeSubtotalRow($sheet, $row, "Subtotal {$label}", $subtotal);

            $totalesGenerales['one_time'] += $subtotal['one_time'];
            $totalesGenerales['on_going'] += $subtotal['on_going'];
            $totalesGenerales['total'] += $subtotal['total'];
        }

        $row++;
        $row = $this->writeSubtotalRow($sheet, $row, 'Subtotales', $totalesGenerales, bold: true);

        $factor = (float) $proyectoPresupuesto->factor_administrativo;
        $row++;
        $this->writeTotalConFactor($sheet, $row, $totalesGenerales, $factor);

        foreach (range('A', 'L') as $columna) {
            $sheet->getColumnDimensionByColumn($this->columnaIndice($columna))->setAutoSize(true);
        }

        $writer = new XlsxWriter($spreadsheet);
        $slug = Str::slug($proyectoPresupuesto->nombre_proyecto) ?: (string) $proyectoPresupuesto->id;

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "presupuesto-proyecto-{$slug}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * `Cantidad × Precio unitario (MXN) × No. Meses`, puesto en "One Time" o
     * "On going" según el `cashflow_tipo` del artículo — mismas fórmulas del
     * Excel real (`=IF(F11=$K$9,(G11*H11*J11),0)` y análoga), generalizadas
     * aquí sin depender de una celda de referencia. Un artículo sin
     * `cashflow_tipo` capturado cae en "One Time" por default (no se
     * silencia el monto solo porque falte ese dato opcional).
     *
     * @return array{one_time: float, on_going: float, total: float}
     */
    private function montosDe(ProyectoPresupuestoArticulo $articulo): array
    {
        $costo = (float) ($articulo->costo_unitario ?? 0);
        $meses = (int) ($articulo->no_meses ?? 1);
        $total = $articulo->cantidad * $costo * $meses;

        $esOnGoing = $articulo->cashflow_tipo === ProyectoPresupuestoArticulo::CASHFLOW_ON_GOING;

        return [
            'one_time' => $esOnGoing ? 0.0 : $total,
            'on_going' => $esOnGoing ? $total : 0.0,
            'total' => $total,
        ];
    }

    private function writeTitulo($sheet, ProyectoPresupuesto $proyectoPresupuesto): int
    {
        $sheet->setCellValueExplicit('A1', $proyectoPresupuesto->nombre_proyecto, DataType::TYPE_STRING);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValueExplicit('A2', $proyectoPresupuesto->empresa?->nombre_comercial ?? '', DataType::TYPE_STRING);
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        return 4;
    }

    private function writeEncabezados($sheet, int $row): int
    {
        $headers = [
            'Bienes y/o Servicios TI', 'Proveedor', 'Razón Social Facturada', 'Tipo de Servicio',
            'CashFlow', 'No. Meses', 'Precio U (MN)', 'Precio U (USD)', 'Cantidad',
            'One Time', 'On going', 'Total',
        ];

        foreach ($headers as $index => $header) {
            $coord = $this->coordenada($index + 1, $row);
            $sheet->setCellValueExplicit($coord, $header, DataType::TYPE_STRING);
        }

        $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');

        return $row + 1;
    }

    private function writeCategoriaHeader($sheet, int $row, string $titulo): int
    {
        $sheet->setCellValueExplicit("A{$row}", $titulo, DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
        $sheet->mergeCells("A{$row}:L{$row}");

        return $row + 1;
    }

    private function writeArticuloRow($sheet, int $row, ProyectoPresupuestoArticulo $articulo, array $montos): int
    {
        $valores = [
            $articulo->descripcion,
            $articulo->proveedor ?? '',
            $articulo->razon_social_facturada ?? '',
            $articulo->tipo_servicio ? (ProyectoPresupuestoArticulo::TIPO_SERVICIO_LABELS[$articulo->tipo_servicio] ?? $articulo->tipo_servicio) : '',
            $articulo->cashflow_tipo ? (ProyectoPresupuestoArticulo::CASHFLOW_LABELS[$articulo->cashflow_tipo] ?? $articulo->cashflow_tipo) : '',
        ];

        foreach ($valores as $index => $valor) {
            $sheet->setCellValueExplicit($this->coordenada($index + 1, $row), (string) $valor, DataType::TYPE_STRING);
        }

        $sheet->setCellValue($this->coordenada(6, $row), (int) ($articulo->no_meses ?? 1));
        $sheet->setCellValue($this->coordenada(7, $row), (float) ($articulo->costo_unitario ?? 0));
        $sheet->setCellValue($this->coordenada(8, $row), $articulo->costo_unitario_usd !== null ? (float) $articulo->costo_unitario_usd : null);
        $sheet->setCellValue($this->coordenada(9, $row), $articulo->cantidad);
        $sheet->setCellValue($this->coordenada(10, $row), $montos['one_time']);
        $sheet->setCellValue($this->coordenada(11, $row), $montos['on_going']);
        $sheet->setCellValue($this->coordenada(12, $row), $montos['total']);

        $sheet->getStyle("G{$row}:L{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);

        return $row + 1;
    }

    private function writeSubtotalRow($sheet, int $row, string $label, array $totales, bool $bold = true): int
    {
        $sheet->setCellValueExplicit("A{$row}", $label, DataType::TYPE_STRING);
        $sheet->setCellValue("J{$row}", $totales['one_time']);
        $sheet->setCellValue("K{$row}", $totales['on_going']);
        $sheet->setCellValue("L{$row}", $totales['total']);
        $sheet->getStyle("J{$row}:L{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);

        if ($bold) {
            $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true);
        }

        return $row + 1;
    }

    /**
     * Bloque final — mismas 3 fórmulas del Excel real generalizadas con el
     * `factor_administrativo` capturado del proyecto en vez de un 1.035 fijo:
     * One Time × factor, On going ÷ 12 × factor (convierte el total anual
     * "on going" a una mensualidad), Total × factor.
     */
    private function writeTotalConFactor($sheet, int $row, array $totalesGenerales, float $factor): void
    {
        $sheet->setCellValueExplicit("A{$row}", "Total con factor administrativo (×{$factor})", DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true);

        $sheet->setCellValue("J{$row}", $totalesGenerales['one_time'] * $factor);
        $sheet->setCellValue("K{$row}", $totalesGenerales['on_going'] / 12 * $factor);
        $sheet->setCellValue("L{$row}", $totalesGenerales['total'] * $factor);
        $sheet->getStyle("J{$row}:L{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
    }

    private function coordenada(int $columnIndex, int $row): string
    {
        return $this->columnaLetra($columnIndex).$row;
    }

    private function columnaLetra(int $columnIndex): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
    }

    private function columnaIndice(string $letra): int
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($letra);
    }
}
