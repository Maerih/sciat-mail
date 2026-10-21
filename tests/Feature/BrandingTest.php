<?php

namespace Tests\Feature;

use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Setting;
use App\Models\Workspace;
use App\Services\Branding;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_white_label_the_platform(): void
    {
        Storage::fake('public');
        $workspace = Workspace::factory()->create();
        $this->signInTo($workspace, 'admin');

        $this->put(route('platform.update'), [
            'platform_name' => 'Acme Mailer', 'platform_color' => '#0f766e', 'logo' => UploadedFile::fake()->image('logo.png', 200, 60),
        ])->assertRedirect();

        $this->assertSame('Acme Mailer', Branding::appName());
        $this->get(route('dashboard'))->assertOk()->assertSee('Acme Mailer')->assertSee('--color-brand-600:#0f766e', false);
        $this->assertCount(1, Storage::disk('public')->files('branding'));

        auth()->logout();
        $this->get(route('login'))->assertSee('Acme Mailer');
    }

    public function test_members_cannot_change_platform_branding(): void
    {
        $this->signInTo(Workspace::factory()->create());

        $this->get(route('platform.edit'))->assertForbidden();
        $this->put(route('platform.update'), ['platform_name' => 'Hijack'])->assertForbidden();
        $this->assertNull(Setting::get('platform_name'));
    }

    public function test_client_brand_appears_on_public_pages_and_signup_emails(): void
    {
        Storage::fake('public');
        $workspace = Workspace::factory()->create(['company' => 'Bloom Bakery']);
        SendingServer::factory()->for($workspace)->create();
        $list = MailList::factory()->for($workspace)->create(['double_optin' => true]);
        $form = $list->forms()->create(['name' => 'Footer']);
        $this->signInTo($workspace);

        $this->put(route('workspaces.update'), [
            'name' => $workspace->name, 'timezone' => 'UTC', 'brand_color' => '#be185d', 'website' => 'https://bloom.example',
            'logo' => UploadedFile::fake()->image('bloom.png', 300, 100),
        ])->assertRedirect();
        $workspace->refresh();
        $this->assertNotNull($workspace->logo_path);

        auth()->logout();
        $this->get(route('subscribe.show', $form->uid))->assertOk()
            ->assertSee(Branding::clientLogo($workspace), false)
            ->assertSee('--color-brand-600:#be185d', false)
            ->assertSee('background:#be185d', false)
            ->assertSee('bloom.example');

        app(SubscriptionService::class)->subscribe($list, 'fan@example.com');
        $html = $this->transport->sent[0]->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString(Branding::clientLogo($workspace), $html);
        $this->assertStringContainsString('background:#be185d', $html);
    }

    public function test_popup_button_follows_client_colour_unless_chosen(): void
    {
        $workspace = Workspace::factory()->create(['brand_color' => '#15803d']);
        $form = MailList::factory()->for($workspace)->create()->forms()->create(['name' => 'Pop', 'display' => 'popup']);

        $this->assertSame('#15803d', $form->popup('color'));
        $form->update(['popup_settings' => ['color' => '#000000']]);
        $this->assertSame('#000000', $form->fresh()->popup('color'));
    }

    public function test_invalid_colours_are_rejected_and_never_reach_css(): void
    {
        $workspace = Workspace::factory()->create();
        $this->signInTo($workspace);

        $this->put(route('workspaces.update'), ['name' => 'x', 'timezone' => 'UTC', 'brand_color' => 'red;}body{display:none'])->assertSessionHasErrors('brand_color');
        $this->assertSame('', Branding::css('red;}body{display:none'));
    }
}
