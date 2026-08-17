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

    public $loginBackground;

    public $appBackground;

    public string $primaryColor = '';

    public string $successColor = '';

    public string $dangerColor = '';

    public string $warningColor = '';

    public string $infoColor = '';

    public string $topbarColor = '';

    public string $sidebarHeaderColor = '';

    public string $sidebarBodyColor = '';

    public string $newPresetName = '';

    public function mount(): void
    {
        $this->fillFromSettings(SiteSetting::current());
    }

    /**
     * Maps DB columns (site_settings / branding_presets) to their Livewire property.
     */
    protected function colorFieldMap(): array
    {
        return [
            'primary_color' => 'primaryColor',
            'success_color' => 'successColor',
            'danger_color' => 'dangerColor',
            'warning_color' => 'warningColor',
            'info_color' => 'infoColor',
            'topbar_color' => 'topbarColor',
            'sidebar_header_color' => 'sidebarHeaderColor',
            'sidebar_body_color' => 'sidebarBodyColor',
        ];
    }

    protected function fillFromSettings(SiteSetting $settings): void
    {
        foreach ($this->colorFieldMap() as $column => $property) {
            $this->$property = $settings->$column;
        }
    }

    protected function colorRules(): array
    {
        $hex = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return array_fill_keys(array_values($this->colorFieldMap()), $hex);
    }

    protected function currentColorData(): array
    {
        $data = [];

        foreach ($this->colorFieldMap() as $column => $property) {
            $data[$column] = $this->$property;
        }

        return $data;
    }

    public function saveColors(): void
    {
        $this->validate($this->colorRules());

        SiteSetting::current()->update($this->currentColorData());

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

    public function saveBackgrounds(): void
    {
        $this->validate([
            'loginBackground' => ['nullable', 'image', 'max:4096'],
            'appBackground' => ['nullable', 'image', 'max:4096'],
        ]);

        $settings = SiteSetting::current();
        $data = [];

        if ($this->loginBackground) {
            if ($settings->login_background_path) {
                Storage::disk('public')->delete($settings->login_background_path);
            }
            $data['login_background_path'] = $this->loginBackground->store('branding', 'public');
        }

        if ($this->appBackground) {
            if ($settings->app_background_path) {
                Storage::disk('public')->delete($settings->app_background_path);
            }
            $data['app_background_path'] = $this->appBackground->store('branding', 'public');
        }

        if ($data) {
            $settings->update($data);
        }

        $this->reset(['loginBackground', 'appBackground']);

        session()->flash('status', 'Fondos de pantalla actualizados.');
    }

    public function removeLoginBackground(): void
    {
        $settings = SiteSetting::current();

        if ($settings->login_background_path) {
            Storage::disk('public')->delete($settings->login_background_path);
            $settings->update(['login_background_path' => null]);
        }

        $this->reset('loginBackground');

        session()->flash('status', 'Fondo de pantallas externas eliminado.');
    }

    public function removeAppBackground(): void
    {
        $settings = SiteSetting::current();

        if ($settings->app_background_path) {
            Storage::disk('public')->delete($settings->app_background_path);
            $settings->update(['app_background_path' => null]);
        }

        $this->reset('appBackground');

        session()->flash('status', 'Fondo del panel interno eliminado.');
    }

    public function removeLogo(): void
    {
        $settings = SiteSetting::current();

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->update(['logo_path' => null]);
        }

        $this->reset('logo');

        session()->flash('status', 'Logotipo eliminado.');
    }

    public function removeFavicon(): void
    {
        $settings = SiteSetting::current();

        if ($settings->favicon_path) {
            Storage::disk('public')->delete($settings->favicon_path);
            $settings->update(['favicon_path' => null]);
        }

        $this->reset('favicon');

        session()->flash('status', 'Favicon eliminado.');
    }

    public function applyPreset(int $presetId): void
    {
        $preset = BrandingPreset::findOrFail($presetId);

        $data = [];
        foreach ($this->colorFieldMap() as $column => $property) {
            $data[$column] = $preset->$column;
        }

        SiteSetting::current()->update($data);

        $this->fillFromSettings(SiteSetting::current());

        session()->flash('status', 'Preset "'.$preset->name.'" aplicado.');
    }

    public function saveAsPreset(): void
    {
        $this->validate(array_merge($this->colorRules(), [
            'newPresetName' => ['required', 'string', 'max:255', 'unique:branding_presets,name'],
        ]));

        BrandingPreset::create(array_merge(
            ['name' => $this->newPresetName],
            $this->currentColorData()
        ));

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
