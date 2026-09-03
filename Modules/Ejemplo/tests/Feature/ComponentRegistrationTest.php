<?php

namespace Modules\Ejemplo\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\ComponentRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Modules\Ejemplo\Livewire\Index;
use Tests\TestCase;

/**
 * Livewire's snapshot->class round trip (Livewire\Mechanisms\ComponentRegistry)
 * always prepends config('livewire.class_namespace') ("App\Livewire") when
 * resolving a component name back to a class. For any component outside that
 * namespace — every module component, including this whole module — the
 * initial page render works (it only needs class->name), but every
 * subsequent Livewire action (wire:click, wire:submit) fails to resolve
 * back to a class and surfaces to the user as a generic "This page has
 * expired" prompt. Livewire::test() never catches this because it bypasses
 * the real /livewire/update HTTP round trip these components go through in
 * production — so this test exercises the registry directly instead.
 */
class ComponentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public static function componentProvider(): array
    {
        return [
            ['ejemplo.index', Index::class],
        ];
    }

    #[DataProvider('componentProvider')]
    public function test_component_name_resolves_back_to_its_class(string $name, string $expectedClass): void
    {
        $resolved = app(ComponentRegistry::class)->getClass($name);

        $this->assertSame($expectedClass, $resolved);
    }
}
