<?php

namespace Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Form extends Model
{
    /**
     * PDF/print templates available from the "Formularios" screen, keyed by
     * a stable identifier stored in forms.pdf_template. Each key must have a
     * matching Blade view at resources/views/pdf/{key}.blade.php. Adding a
     * template for a new form = one entry here + its view, no route/code
     * changes tied to a specific form.
     */
    public const PDF_TEMPLATES = [
        'alta_usuario' => 'Alta de Usuario (Solicitud de accesos)',
        'oracle_responsabilidades' => 'Solicitud Responsabilidades Oracle',
        'oracle_flujo_aprobacion' => 'Solicitud de Flujo de Aprobación Oracle',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'pdf_template',
        'created_by',
    ];

    /**
     * The Blade view for this form's PDF/print output — its assigned
     * template if valid and present, otherwise the generic field:value
     * fallback used by every other form.
     */
    public function pdfView(): string
    {
        $key = $this->pdf_template;
        $view = ($key && array_key_exists($key, self::PDF_TEMPLATES)) ? "formbuilder::pdf.{$key}" : null;

        return ($view && view()->exists($view)) ? $view : 'formbuilder::pdf.generic';
    }

    /**
     * File name for a PDF/print download of one of this form's responses.
     * Based on the editable `slug` (see "Nombre de descarga" in
     * Forms\Manage) rather than `name` directly, since the audit-driven
     * control-number names (e.g. "FMSOL001-MultiFormatoSolicitud") are too
     * long for a comfortable downloaded file name.
     */
    public function downloadFilename(string $ticketNumber): string
    {
        return "{$this->slug}-{$ticketNumber}.pdf";
    }

    protected static function booted(): void
    {
        static::creating(function (Form $form) {
            $form->slug ??= static::uniqueSlugFrom($form->name);
        });
    }

    protected static function uniqueSlugFrom(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->whereNull('parent_field_id')->orderBy('order')->with('children');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function ticketFormLinks(): HasMany
    {
        return $this->hasMany(TicketFormLink::class);
    }

    public function scopeWherePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
