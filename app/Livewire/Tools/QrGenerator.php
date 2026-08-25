<?php

namespace App\Livewire\Tools;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QrGenerator extends Component
{
    public function render()
    {
        return view('livewire.tools.qr-generator');
    }
}
