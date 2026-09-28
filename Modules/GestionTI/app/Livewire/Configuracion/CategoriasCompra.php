<?php

namespace Modules\GestionTI\Livewire\Configuracion;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionTI\Models\ConfiguracionCategorias;
use Modules\GestionTI\Support\Catalogos\CategoriaArticulo;

/**
 * "Categorías que van a Compra" — decide, de las 11 categorías de
 * `CategoriaArticulo`, cuáles representan una compra real de equipo físico
 * que debe pasar por Compras vía "Solicitud a Proveedores" (picker de SICs
 * autorizadas y disponibles). Pantalla de configuración pura, sin
 * listado/paginación, mismo patrón exacto que `AlmacenamientoDocumentos`
 * sobre `ConfiguracionDocumentos::current()`.
 */
#[Layout('layouts.app')]
class CategoriasCompra extends Component
{
    /** @var array<int, string> */
    public array $categoriasCompra = [];

    public function mount(): void
    {
        $this->categoriasCompra = ConfiguracionCategorias::current()->categorias_compra ?? [];
    }

    public function save(): void
    {
        $this->validate([
            'categoriasCompra' => 'array',
            'categoriasCompra.*' => ['string', Rule::in(CategoriaArticulo::OPTIONS)],
        ]);

        ConfiguracionCategorias::current()->update([
            'categorias_compra' => array_values($this->categoriasCompra),
        ]);

        session()->flash('status', 'Categorías que van a Compra actualizadas.');
    }

    public function render()
    {
        return view('gestionti::livewire.configuracion.categorias-compra', [
            'categorias' => CategoriaArticulo::OPTIONS,
            'labels' => CategoriaArticulo::LABELS,
        ]);
    }
}
