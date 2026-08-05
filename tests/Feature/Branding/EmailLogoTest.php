<?php

namespace Tests\Feature\Branding;

use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_emails_fall_back_to_the_site_name_when_no_logo_is_set(): void
    {
        $user = User::factory()->create();

        $html = (new LoginCodeNotification('123456'))->toMail($user)->render();

        $this->assertStringContainsString(config('app.name'), $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_emails_show_the_logo_when_one_is_configured(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', 'fake-image-content');

        SiteSetting::current()->update(['logo_path' => 'branding/logo.png']);

        $user = User::factory()->create();

        $html = (new LoginCodeNotification('123456'))->toMail($user)->render();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('branding/logo.png', $html);
    }
}
