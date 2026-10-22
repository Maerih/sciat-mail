<?php

namespace Tests\Feature;

use App\Models\MailList;
use App\Models\Setting;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    public function test_forms_require_a_valid_turnstile_token_when_enabled(): void
    {
        $list = MailList::factory()->for(Workspace::factory()->create())->create();
        $form = $list->forms()->create(['name' => 'Footer']);
        Setting::put(['captcha_provider' => 'turnstile', 'captcha_site_key' => '0x4AAA-site', 'captcha_secret' => Crypt::encryptString('0x4AAA-secret')]);

        $this->get(route('subscribe.show', $form->uid))->assertOk()->assertSee('sm-captcha', false)->assertSee('challenges.cloudflare.com', false);

        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);
        $this->postJson(route('subscribe.store', $form->uid), ['EMAIL' => 'bot@example.com', 'cf-turnstile-response' => 'bad'])
            ->assertUnprocessable()->assertJsonPath('errors.captcha.0', 'Please confirm you are not a robot.');
        $this->postJson(route('subscribe.store', $form->uid), ['EMAIL' => 'human@example.com', 'cf-turnstile-response' => 'good'])->assertOk();

        $this->assertSame(['human@example.com'], $list->subscribers()->pluck('email')->all());
        Http::assertSent(fn ($r) => $r['secret'] === '0x4AAA-secret' && $r['response'] === 'good');
    }

    public function test_forms_work_without_captcha_when_off(): void
    {
        $list = MailList::factory()->for(Workspace::factory()->create())->create();
        $form = $list->forms()->create(['name' => 'Footer']);

        $this->get(route('subscribe.show', $form->uid))->assertOk()->assertDontSee('sm-captcha', false);
        $this->postJson(route('subscribe.store', $form->uid), ['EMAIL' => 'pat@example.com'])->assertOk();
    }
}
