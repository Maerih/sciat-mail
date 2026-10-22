<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_are_uploaded_listed_and_deleted_per_client(): void
    {
        Storage::fake('public');
        $workspace = Workspace::factory()->create();
        $this->signInTo($workspace);

        $this->post(route('assets.store'), ['files' => [UploadedFile::fake()->image('logo.png'), UploadedFile::fake()->create('menu.pdf', 50, 'application/pdf')]])
            ->assertSessionHas('success');
        $files = Storage::disk('public')->files('uploads/'.$workspace->uid);
        $this->assertCount(2, $files);

        $image = basename(collect($files)->first(fn ($f) => str_ends_with($f, '.png')));
        $this->get(route('assets.index'))->assertOk()->assertSee($image);
        $this->get(route('assets.index', ['type' => 'files']))->assertOk()->assertDontSee($image);

        // Another client never sees this client's files
        $this->signInTo(Workspace::factory()->create());
        $this->get(route('assets.index'))->assertOk()->assertDontSee($image);

        $this->signInTo($workspace);
        $this->delete(route('assets.destroy', $image))->assertRedirect();
        $this->assertCount(1, Storage::disk('public')->files('uploads/'.$workspace->uid));
    }

    public function test_only_images_and_pdfs_are_accepted_and_paths_cannot_escape_the_folder(): void
    {
        Storage::fake('public');
        $this->signInTo(Workspace::factory()->create());

        $this->post(route('assets.store'), ['files' => [UploadedFile::fake()->create('shell.php', 1, 'application/x-php')]])->assertSessionHasErrors('files.0');
        $this->delete('/assets/..%2F..%2F.env')->assertNotFound();
    }
}
