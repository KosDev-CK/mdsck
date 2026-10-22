<?php

namespace Modules\GestionTI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Línea de una requisición de EBS atendida fuera del flujo — ver la
 * migración `create_ebs_lineas_atendidas_table`. "EbsLineaAtendida" ->
 * "ebs_linea_atendidas" no es la tabla real, por eso `$table` explícito.
 */
class EbsLineaAtendida extends Model
{
    protected $table = 'ebs_lineas_atendidas';

    protected $fillable = ['ebs_requisition_line_id', 'ebs_requisition_id', 'motivo', 'user_id'];

    public function ebsRequisition()
    {
        return $this->belongsTo(EbsRequisition::class, 'ebs_requisition_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
