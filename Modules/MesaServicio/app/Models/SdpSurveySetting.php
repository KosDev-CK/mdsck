<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\FormBuilder\Models\Form;

/**
 * Singleton (mismo patrón que App\Models\SiteSetting::current()) que decide
 * cuál Form publicado de Modules\FormBuilder es la encuesta de satisfacción
 * disparada automáticamente por sdp:sync-tickets cuando un ticket pasa a
 * "completado". Mientras form_id sea null, el disparo automático está
 * completamente desactivado — comportamiento seguro por defecto, no se
 * manda ningún correo hasta que se configure explícitamente desde
 * Livewire\Catalogos\Destinatarios.
 */
class SdpSurveySetting extends Model
{
    protected $table = 'sdp_survey_settings';

    protected $fillable = [
        'form_id',
    ];

    /**
     * Nombre de campo estable ('field_key') que debe llevar la pregunta de
     * calificación (1-5) del formulario configurado como encuesta, para que
     * Livewire\Tecnicos\Show pueda localizarla sin depender de su posición
     * ordinal ni de un texto de label que pudiera cambiar. El script que crea
     * el contenido de la encuesta (ver docs/mesaservicio-progreso.md, Fase 6)
     * usa este mismo valor al crear el FormField correspondiente.
     */
    public const CALIFICACION_FIELD_KEY = 'satisfaccion_calificacion';

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
