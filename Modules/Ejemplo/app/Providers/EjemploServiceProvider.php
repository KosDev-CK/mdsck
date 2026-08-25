<?php

namespace Modules\Ejemplo\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Modules\Ejemplo\Livewire\Index;
use Nwidart\Modules\Support\ModuleServiceProvider;

class EjemploServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Ejemplo';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'ejemplo';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        /**
         * Livewire's component name<->class round trip
         * (Livewire\Mechanisms\ComponentRegistry::generateClassFromName()) always
         * prepends config('livewire.class_namespace') ("App\Livewire" by default)
         * when resolving a snapshot's name back to a class. It only *strips* that
         * prefix on the forward direction if the class actually lives under it —
         * so for any component outside App\Livewire (every module component),
         * the initial page render works, but every subsequent Livewire action
         * (wire:click, wire:submit, ...) fails server-side component resolution
         * and surfaces to the user as a generic "This page has expired" prompt.
         * An explicit alias sidesteps the broken reverse-lookup entirely — copy
         * this pattern (one Livewire::component() call per full-page component)
         * into every module cloned from this one, see
         * Livewire\Mechanisms\ComponentRegistry.
         */
        Livewire::component('ejemplo.index', Index::class);
    }

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
