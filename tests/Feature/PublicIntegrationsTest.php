<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class PublicIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfigured_integrations_are_hidden_and_password_mail_is_not_attempted(): void
    {
        config(['services.google.client_id' => null, 'services.mailgun.domain' => null, 'services.mailgun.secret' => null]);
        Notification::fake();
        Mail::fake();
        $this->get('/login')->assertOk()->assertDontSee('Continue with Google')->assertDontSee('Forgot your password?');
        $this->get('/login/google')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'test@example.com'])->assertNotFound();
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_branding_and_contact_are_available_without_email(): void
    {
        config(['app.name' => 'Example Author', 'app.logo' => '/custom-logo.svg', 'app.favicon' => '/custom-icon.svg']);
        $this->get('/')->assertOk()->assertSee('Example Author')->assertSee('/custom-logo.svg')->assertSee('/custom-icon.svg');
        $this->get('/privacy')->assertOk()->assertSee('Example Author');
        $this->get('/terms')->assertOk()->assertSee('Example Author');
        Mail::fake();
        Notification::fake();
        $this->from('/login')->post('/contact', ['name' => 'A Writer', 'email' => 'writer@example.com', 'subject' => 'Help', 'message' => 'A question.'])->assertRedirect('/login')->assertSessionHas('contact_status');
        $this->assertDatabaseHas('contact_messages', ['email' => 'writer@example.com', 'message' => 'A question.']);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    private function googleConfig(): void
    {
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost/login/google/callback']);
    }

    private function profile(string $email, bool $verified = true): void
    {
        $profile = (new GoogleUser)->setRaw(['email_verified' => $verified])->map(['id' => 'google-123', 'name' => 'Google Writer', 'email' => $email]);
        $provider = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($profile);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_signup_and_repeat_login_use_provider_identity(): void
    {
        $this->googleConfig();
        $this->get('/login')->assertSee('Continue with Google');
        $this->profile('new@example.com');
        $this->get('/login/google/callback')->assertRedirect('/dashboard');
        $user = User::where('google_id', 'google-123')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('paper', $user->theme);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_google_does_not_take_over_existing_email_accounts(): void
    {
        $this->googleConfig();
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $this->profile($user->email);
        $this->get('/login/google/callback')->assertRedirect('/login')->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->googleConfig();
        $this->profile('new@example.com', false);
        $this->get('/login/google/callback')->assertRedirect('/login')->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_mail_events_are_cancelled_without_credentials(): void
    {
        config(['services.mailgun.domain' => null, 'services.mailgun.secret' => null]);
        $message = (new \Symfony\Component\Mime\Email)->from('a@example.com')->to('b@example.com')->text('Test');
        $this->assertFalse(event(new \Illuminate\Mail\Events\MessageSending($message, []), [], true));
    }
}
