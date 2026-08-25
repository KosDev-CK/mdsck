<?php

namespace Modules\FormBuilder\Livewire\Links;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\FormBuilder\Models\TicketFormLink;

#[Layout('layouts.app')]
class Show extends Component
{
    public TicketFormLink $ticketFormLink;

    public function mount(TicketFormLink $ticketFormLink)
    {
        abort_unless(
            $ticketFormLink->created_by === auth()->id() || auth()->user()->hasRole('Administrador'),
            403
        );

        $this->ticketFormLink = $ticketFormLink;
    }

    public function render()
    {
        $this->ticketFormLink->load(['form.fields', 'submission.answers']);

        return view('formbuilder::livewire.links.show');
    }
}
