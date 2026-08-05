<?php

namespace Tests\Feature\Branding;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_invitation_email_has_no_leftover_english_boilerplate(): void
    {
        $invitation = Invitation::create([
            'name' => 'Mac',
            'email' => 'mac@example.com',
            'token_hash' => 'x',
            'invited_by' => User::factory()->create()->id,
            'expires_at' => now()->addDays(7),
        ]);

        $html = (new UserInvitationNotification($invitation, 'raw-token'))
            ->toMail(new User(['name' => 'Mac']))
            ->render();

        $this->assertStringNotContainsString('Regards,', $html);
        $this->assertStringNotContainsString('having trouble', $html);
        $this->assertStringContainsString('Saludos,', $html);
        $this->assertStringContainsString('Si tienes problemas', $html);
    }
}
