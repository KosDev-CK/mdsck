<?php

namespace Modules\GestionTI\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\AssetCompliance;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\TipoEquipo;
use Tests\TestCase;

class AssetSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makeAsset(): Asset
    {
        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $estatus = EstatusActivo::create(['codigo' => 'en_stock', 'nombre' => 'En stock']);

        return Asset::create([
            'codigo' => 'KOS-LAPTOP-000001',
            'tipo_equipo_id' => $tipoEquipo->id,
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $estatus->id,
            'especificaciones' => [
                'ram' => '16GB',
                'disco' => '512GB SSD',
                'procesador' => 'Intel i7',
                'hostname' => 'KOS-LAP-001',
                'mac_wifi' => 'AA:BB:CC:DD:EE:FF',
                'mac_ethernet' => '11:22:33:44:55:66',
                'usuario_dominio' => 'jperez',
                'equipo_en_dominio' => true,
            ],
        ]);
    }

    public function test_can_create_an_asset_with_its_required_relations(): void
    {
        $asset = $this->makeAsset();

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'codigo' => 'KOS-LAPTOP-000001',
            'origen_tipo' => 'migracion_historica',
        ]);

        $this->assertInstanceOf(TipoEquipo::class, $asset->tipoEquipo);
        $this->assertInstanceOf(EstatusActivo::class, $asset->estatus);
        $this->assertSame('Laptop', $asset->tipoEquipo->nombre);
        $this->assertSame('en_stock', $asset->estatus->codigo);
    }

    public function test_especificaciones_casts_to_and_from_array(): void
    {
        $asset = $this->makeAsset();

        $this->assertIsArray($asset->especificaciones);
        $this->assertSame('16GB', $asset->especificaciones['ram']);
        $this->assertTrue($asset->especificaciones['equipo_en_dominio']);

        $fresh = $asset->fresh();
        $this->assertIsArray($fresh->especificaciones);
        $this->assertSame('Intel i7', $fresh->especificaciones['procesador']);
    }

    public function test_tipo_equipo_has_many_assets_inverse_relation(): void
    {
        $asset = $this->makeAsset();

        $this->assertTrue($asset->tipoEquipo->assets->contains($asset));
    }

    public function test_can_create_an_asset_compliance_for_an_asset(): void
    {
        $asset = $this->makeAsset();

        $compliance = AssetCompliance::create([
            'asset_id' => $asset->id,
            'crowdstrike' => true,
            'crowdstrike_fecha' => '2026-01-15',
            'bitlocker' => false,
        ]);

        $this->assertInstanceOf(Asset::class, $compliance->asset);
        $this->assertSame($asset->id, $compliance->asset->id);
        $this->assertTrue($compliance->crowdstrike);
        $this->assertFalse($compliance->bitlocker);
    }

    public function test_asset_compliance_enforces_one_record_per_asset(): void
    {
        $asset = $this->makeAsset();

        AssetCompliance::create(['asset_id' => $asset->id]);

        $this->expectException(QueryException::class);

        AssetCompliance::create(['asset_id' => $asset->id]);
    }

    /**
     * FK cerrada en Fase 3 etapa 3 (`recepcion_linea_id` no tenía constraint
     * de BD hasta que `recepcion_lineas` existió) — ver
     * docs/gestionti-progreso.md. Aserción a nivel de esquema, no solo de
     * comportamiento de aplicación.
     */
    public function test_recepcion_linea_id_foreign_key_is_enforced(): void
    {
        $asset = $this->makeAsset();

        $this->expectException(QueryException::class);

        $asset->update(['recepcion_linea_id' => 999999]);
    }

    /**
     * Misma historia que la FK de arriba — `sic_reservada_id` apuntaba a
     * `solicitudes_sic_borrador`, tabla que no existía cuando se escribió el
     * esquema de Fase 2.
     */
    public function test_sic_reservada_id_foreign_key_is_enforced(): void
    {
        $asset = $this->makeAsset();

        $this->expectException(QueryException::class);

        $asset->update(['sic_reservada_id' => 999999]);
    }

    public function test_can_create_an_asset_assignment_for_an_asset(): void
    {
        $asset = $this->makeAsset();
        $empleado = Empleado::create([
            'numero_empleado' => 'EMP-001',
            'nombre' => 'Juan Pérez',
        ]);

        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $empleado->id,
            'fecha_asignacion' => '2026-02-01',
            'estado_equipo_entrega' => 'nuevo',
        ]);

        $this->assertInstanceOf(Asset::class, $assignment->asset);
        $this->assertInstanceOf(Empleado::class, $assignment->empleado);
        $this->assertSame($asset->id, $assignment->asset->id);
        $this->assertSame($empleado->id, $assignment->empleado->id);
        $this->assertTrue($empleado->assetAssignments->contains($assignment));
    }

    /**
     * FKs cerradas en Fase 3 etapa 4 (Asignación de Activo) — ver
     * docs/gestionti-progreso.md. `ticket_id`/`sic_id`/`documento_responsiva_id`
     * no tenían constraint de BD hasta que sus tablas existieron.
     */
    public function test_ticket_id_foreign_key_is_enforced(): void
    {
        $asset = $this->makeAsset();
        $empleado = Empleado::create(['numero_empleado' => 'EMP-100', 'nombre' => 'Ana López']);
        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $empleado->id,
            'fecha_asignacion' => '2026-02-01',
            'estado_equipo_entrega' => 'nuevo',
        ]);

        $this->expectException(QueryException::class);

        $assignment->update(['ticket_id' => 999999]);
    }

    public function test_sic_id_foreign_key_is_enforced(): void
    {
        $asset = $this->makeAsset();
        $empleado = Empleado::create(['numero_empleado' => 'EMP-101', 'nombre' => 'Ana López']);
        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $empleado->id,
            'fecha_asignacion' => '2026-02-01',
            'estado_equipo_entrega' => 'nuevo',
        ]);

        $this->expectException(QueryException::class);

        $assignment->update(['sic_id' => 999999]);
    }

    public function test_documento_responsiva_id_foreign_key_is_enforced(): void
    {
        $asset = $this->makeAsset();
        $empleado = Empleado::create(['numero_empleado' => 'EMP-102', 'nombre' => 'Ana López']);
        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $empleado->id,
            'fecha_asignacion' => '2026-02-01',
            'estado_equipo_entrega' => 'nuevo',
        ]);

        $this->expectException(QueryException::class);

        $assignment->update(['documento_responsiva_id' => 999999]);
    }

    /**
     * FK cerrada en Fase 3, etapa "Presupuesto por Proyecto" — ver
     * docs/gestionti-progreso.md. `proyecto_presupuesto_id` no tenía
     * constraint de BD hasta que `proyecto_presupuestos` existió.
     */
    public function test_proyecto_presupuesto_id_foreign_key_is_enforced_on_assets(): void
    {
        $asset = $this->makeAsset();

        $this->expectException(QueryException::class);

        $asset->update(['proyecto_presupuesto_id' => 999999]);
    }

    /**
     * Misma historia que la FK de arriba —
     * `solicitudes_proveedor.proyecto_presupuesto_articulo_id` apuntaba a
     * `proyecto_presupuesto_articulos`, tabla que no existía cuando se
     * escribió el esquema de `solicitudes_proveedor` (Fase 3, etapa 2).
     */
    public function test_proyecto_presupuesto_articulo_id_foreign_key_is_enforced_on_solicitudes_proveedor(): void
    {
        $vendor = Proveedor::create(['razon_social' => 'Proveedor de Prueba', 'nombre_comercial' => 'Proveedor de Prueba']);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-SCHEMA-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);

        $this->expectException(QueryException::class);

        $solicitud->update(['proyecto_presupuesto_articulo_id' => 999999]);
    }

    /**
     * FK cerrada en Fase 3, etapa 6 (Facturación) — ver
     * docs/gestionti-progreso.md. `invoice_id` no tenía constraint de BD
     * hasta que `invoices` existió.
     */
    public function test_invoice_id_foreign_key_is_enforced_on_assets(): void
    {
        $asset = $this->makeAsset();

        $this->expectException(QueryException::class);

        $asset->update(['invoice_id' => 999999]);
    }
}
