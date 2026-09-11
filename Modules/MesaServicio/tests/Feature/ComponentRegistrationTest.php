<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\ComponentRegistry;
use Modules\MesaServicio\Livewire\Catalogos\Destinatarios;
use Modules\MesaServicio\Livewire\Catalogos\Tecnicos;
use Modules\MesaServicio\Livewire\Dashboard;
use Modules\MesaServicio\Livewire\Reportes\Index as ReportesIndex;
use Modules\MesaServicio\Livewire\Tecnicos\Show as TecnicoShow;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ver Modules/GestionTI/tests/Feature/ComponentRegistrationTest.php (o
 * Modules/FormBuilder/tests/Feature/ComponentRegistrationTest.php) para el
 * porqué de este test: Livewire::test() no detecta el bug de resolución de
 * componentes fuera de App\Livewire, así que este test ejercita el registro
 * directamente en vez de confiar en Livewire::test().
 */
class ComponentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public static function componentProvider(): array
    {
        return [
            ['mesaservicio.catalogos.tecnicos', Tecnicos::class],
            ['mesaservicio.catalogos.destinatarios', Destinatarios::class],
            ['mesaservicio.dashboard', Dashboard::class],
            ['mesaservicio.tecnicos.show', TecnicoShow::class],
            ['mesaservicio.reportes.index', ReportesIndex::class],
        ];
    }

    #[DataProvider('componentProvider')]
    public function test_component_name_resolves_back_to_its_class(string $name, string $expectedClass): void
    {
        $resolved = app(ComponentRegistry::class)->getClass($name);

        $this->assertSame($expectedClass, $resolved);
    }
}
