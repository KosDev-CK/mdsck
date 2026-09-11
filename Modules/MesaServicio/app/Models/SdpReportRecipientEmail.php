<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Model;

class SdpReportRecipientEmail extends Model
{
    protected $table = 'sdp_report_recipient_emails';

    protected $fillable = [
        'email',
        'nombre',
    ];
}
