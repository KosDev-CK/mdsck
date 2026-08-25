<?php

namespace Modules\FormBuilder\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Modules\FormBuilder\Livewire\Forms\Builder;
use Modules\FormBuilder\Livewire\Forms\Manage as FormsManage;
use Modules\FormBuilder\Livewire\Links\Send as LinksSend;
use Modules\FormBuilder\Livewire\Links\Show as LinksShow;
use Modules\FormBuilder\Livewire\Public\FillTicketForm;
use Nwidart\Modules\Support\ModuleServiceProvider;

class FormBuilderServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'FormBuilder';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'formbuilder';

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
         * so for any component outside App\Livewire (every module component,
         * including this one), the initial page render works, but every
         * subsequent Livewire action (wire:click, wire:submit, ...) fails
         * server-side component resolution and surfaces to the user as a
         * "This page has expired" prompt. Explicit aliases sidestep the broken
         * reverse-lookup entirely — see Livewire\Mechanisms\ComponentRegistry.
         */
        Livewire::component('formbuilder.forms.manage', FormsManage::class);
        Livewire::component('formbuilder.forms.builder', Builder::class);
        Livewire::component('formbuilder.links.send', LinksSend::class);
        Livewire::component('formbuilder.links.show', LinksShow::class);
        Livewire::component('formbuilder.public.fill-ticket-form', FillTicketForm::class);
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
