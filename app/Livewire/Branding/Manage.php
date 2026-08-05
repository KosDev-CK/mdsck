<?php

namespace App\Livewire\Branding;

use App\Models\BrandingPreset;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Manage extends Component
{
    use WithFileUploads;

    public $logo;

    public $favicon;

    public string $primaryColor = '';

    public string $successColor = '';

    public string $dangerColor = '';

    public string $warningColor = '';

    public string $infoColor = '';

    public string $newPresetName = '';

    public function mount(): void
    {
        $this->fillFromSettings(SiteSetting::current());
    }

    protected function fillFromSettings(SiteSetting $settings): void
    {
        $this->primaryColor = $settings->primary_color;
        $this->successColor = $settings->success_color;
        $this->dangerColor = $settings->danger_color;
        $this->warningColor = $settings->warning_color;
        $this->infoColor = $settings->info_color;
    }

    protected function colorRules(): array
    {
        $hex = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'primaryColor' => $hex,
            'successColor' => $hex,
            'dangerColor' => $hex,
            'warningColor' => $hex,
            'infoColor' => $hex,
        ];
    }

    public function saveColors(): void
    {
        $this->validate($this->colorRules());

        SiteSetting::current()->update([
            'primary_color' => $this->primaryColor,
            'success_color' => $this->successColor,
            'danger_color' => $this->dangerColor,
            'warning_color' => $this->warningColor,
            'info_color' => $this->infoColor,
        ]);

        session()->flash('status', 'Colores actualizados.');
    }

    public function saveIdentity(): void
    {
        $this->validate([
            'logo' => ['nullable', 'image', 'max:1024'],
            'favicon' => ['nullable', 'image', 'max:512'],
        ]);

        $settings = SiteSetting::current();
        $data = [];

        if ($this->logo) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $data['logo_path'] = $this->logo->store('branding', 'public');
        }

        if ($this->favicon) {
            if ($settings->favicon_path) {
                Storage::disk('public')->delete($settings->favicon_path);
            }
            $data['favicon_path'] = $this->favicon->store('branding', 'public');
        }

        if ($data) {
            $settings->update($data);
        }

        $this->reset(['logo', 'favicon']);

        session()->flash('status', 'Identidad visual actualizada.');
    }

    public function applyPreset(int $presetId): void
    {
        $preset = BrandingPreset::findOrFail($presetId);

        SiteSetting::current()->update([
            'primary_color' => $preset->primary_color,
            'success_color' => $preset->success_color,
            'danger_color' => $preset->danger_color,
            'warning_color' => $preset->warning_color,
            'info_color' => $preset->info_color,
        ]);

        $this->fillFromSettings(SiteSetting::current());

        session()->flash('status', 'Preset "'.$preset->name.'" aplicado.');
    }

    public function saveAsPreset(): void
    {
        $this->validate(array_merge($this->colorRules(), [
            'newPresetName' => ['required', 'string', 'max:255', 'unique:branding_presets,name'],
        ]));

        BrandingPreset::create([
            'name' => $this->newPresetName,
            'primary_color' => $this->primaryColor,
            'success_color' => $this->successColor,
            'danger_color' => $this->dangerColor,
            'warning_color' => $this->warningColor,
            'info_color' => $this->infoColor,
        ]);

        $this->newPresetName = '';

        session()->flash('status', 'Preset guardado.');
    }

    public function deletePreset(int $presetId): void
    {
        $preset = BrandingPreset::findOrFail($presetId);

        if ($preset->is_system) {
            return;
        }

        $preset->delete();
    }

    public function render()
    {
        return view('livewire.branding.manage', [
            'settings' => SiteSetting::current(),
            'presets' => BrandingPreset::orderByDesc('is_system')->orderBy('name')->get(),
        ]);
    }
}
