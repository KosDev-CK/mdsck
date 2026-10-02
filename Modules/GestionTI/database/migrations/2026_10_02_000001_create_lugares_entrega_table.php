<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo real "Lugar de entrega" — campo nuevo por línea de
 * `SolicitudProveedorLinea` (pestaña "Lugar de entrega" de "Catálogos de
 * Compras", ver `Modules\GestionTI\Livewire\Catalogos\Compras::catalogos()`),
 * a petición explícita del usuario tras ver el mockup de "Líneas del pedido"
 * como tabla compacta. Mismo patrón que la conversión de Categoría
 * (`2026_09_30_000001_convert_categoria_articulo_to_real_catalog.php`): se
 * siembran los 3 lugares reales incondicionalmente, en cualquier ambiente
 * incluida producción, para que no haga falta captura manual en ningún lado.
 */
return new class extends Migration
{
    private const LUGARES = ['Zurich', 'CEDA', 'Sotelo'];

    public function up(): void
    {
        Schema::create('lugares_entrega', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        foreach (self::LUGARES as $nombre) {
            DB::table('lugares_entrega')->insert([
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lugares_entrega');
    }
};
