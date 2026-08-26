<?php

namespace Modules\FormBuilder\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FormBuilder\Models\TicketFormLink;

class TicketFormLinkPdfController extends Controller
{
    /**
     * Internal "Imprimir" — same auth as Links\Show, plus creator-or-Administrador.
     */
    public function internal(TicketFormLink $ticketFormLink)
    {
        abort_unless($ticketFormLink->viewableBy(auth()->user()), 403);

        return $this->render($ticketFormLink);
    }

    /**
     * Public "Imprimir" — no auth, authorized entirely by the signed URL
     * (see FillTicketForm::printUrl()); the "signed" route middleware
     * already rejects an invalid/expired signature before this runs.
     */
    public function public(TicketFormLink $ticketFormLink)
    {
        abort_unless($ticketFormLink->isUsed(), 404);

        return $this->render($ticketFormLink);
    }

    protected function render(TicketFormLink $ticketFormLink)
    {
        $ticketFormLink->load(['form.fields', 'submission.answers']);

        return view($ticketFormLink->form->pdfView(), [
            'form' => $ticketFormLink->form,
            'link' => $ticketFormLink,
        ]);
    }
}
