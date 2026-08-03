<?php

namespace App\Livewire\Modules;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Nwidart\Modules\Facades\Module;

#[Layout('layouts.app')]
class Manage extends Component
{
    public function toggle(string $name)
    {
        $module = Module::find($name);

        if (! $module) {
            return;
        }

        $module->isEnabled() ? $module->disable() : $module->enable();
    }

    public function render()
    {
        return view('livewire.modules.manage', [
            'modules' => Module::all(),
        ]);
    }
}
