<?php

namespace Modules\FormBuilder\Livewire\Links;

use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\FormBuilder\Models\TicketFormLink;

#[Layout('layouts.app')]
class Show extends Component
{
    public TicketFormLink $ticketFormLink;

    public function mount(TicketFormLink $ticketFormLink)
    {
        abort_unless($ticketFormLink->viewableBy(auth()->user()), 403);

        $this->ticketFormLink = $ticketFormLink;
    }

    public function exportPdf()
    {
        $link = $this->ticketFormLink;
        $form = $link->form;

        $pdf = Pdf::loadView($form->pdfView(), ['form' => $form, 'link' => $link]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            $form->downloadFilename($link->ticket_number)
        );
    }

    public function render()
    {
        $this->ticketFormLink->load(['form.fields', 'submission.answers']);

        return view('formbuilder::livewire.links.show');
    }
}
