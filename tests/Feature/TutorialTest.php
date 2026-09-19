<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Tutorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TutorialTest extends TestCase
{
    use RefreshDatabase;

    /** Llave opcional del panel oculto usada en las pruebas. */
    private const KEY = 'llave-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();

        // Por defecto el panel se abre solo con la URL (sin llave).
        config(['portal.tutorials_admin_key' => '']);
        Storage::fake(Tutorial::DISK);
    }

    private function client(): Client
    {
        return Client::create([
            'name' => 'Cliente Demo',
            'tax_id' => 'XAXX010101000',
        ]);
    }

    /** URL simple del panel (sin parámetros). */
    private function manageUrl(): string
    {
        return '/gestion-tutoriales';
    }

    public function test_tutorials_section_lists_only_visible_ones_with_embed_url(): void
    {
        Tutorial::create([
            'title' => 'Cómo pagar',
            'kind' => Tutorial::KIND_LINK,
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_active' => true,
        ]);

        Tutorial::create([
            'title' => 'Borrador',
            'kind' => Tutorial::KIND_LINK,
            'url' => 'https://example.com/manual.pdf',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->client(), 'portal')->get(route('tutorials.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tutorials/Index')
            ->has('tutorials', 1)
            ->where('tutorials.0.title', 'Cómo pagar')
            ->where('tutorials.0.media_type', 'embed')
            ->where('tutorials.0.embed', 'https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0')
        );
    }

    public function test_manage_panel_is_hidden_and_needs_a_portal_session(): void
    {
        // Sin sesión del portal: al login.
        $this->get($this->manageUrl())->assertRedirect(route('login'));

        // Sin llave configurada se entra solo con la URL (no está enlazada en ninguna pantalla).
        $this->actingAs($this->client(), 'portal')->get($this->manageUrl())->assertOk();
    }

    public function test_manage_panel_requires_the_key_once_when_it_is_configured(): void
    {
        config(['portal.tutorials_admin_key' => self::KEY]);

        $client = $this->client();

        // Sin llave (o con una incorrecta) el panel no existe: 404.
        $this->actingAs($client, 'portal')->get($this->manageUrl())->assertNotFound();
        $this->actingAs($client, 'portal')->get($this->manageUrl().'?llave=incorrecta')->assertNotFound();

        // Con la llave correcta se autoriza una vez y queda recordada en la sesión.
        $this->actingAs($client, 'portal')->get($this->manageUrl().'?llave='.self::KEY)->assertOk();
        $this->actingAs($client, 'portal')->get($this->manageUrl())->assertOk();
    }

    public function test_oversized_upload_returns_to_the_panel_with_an_explanation(): void
    {
        // PHP descarta el POST completo cuando supera `post_max_size` y Laravel
        // responde 413; para el panel se redirige con `?error=size`.
        $this->actingAs($this->client(), 'portal')
            ->withServerVariables(['CONTENT_LENGTH' => 999999999])
            ->post($this->manageUrl())
            ->assertRedirect($this->manageUrl().'?error=size');

        $this->assertSame(0, Tutorial::count());
    }

    public function test_manage_panel_shows_the_oversized_warning(): void
    {
        $this->actingAs($this->client(), 'portal')
            ->get($this->manageUrl().'?error=size')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tutorials/Manage')
                ->where('oversized', true)
            );
    }

    public function test_admin_can_upload_a_video_tutorial_with_poster(): void
    {
        $response = $this->actingAs($this->client(), 'portal')->post($this->manageUrl(), [
            'title' => 'Cómo registrar un pago',
            'description' => 'Paso a paso',
            'kind' => Tutorial::KIND_FILE,
            'sort_order' => 3,
            'file' => UploadedFile::fake()->create('tutorial.mp4', 2048, 'video/mp4'),
            'poster' => UploadedFile::fake()->create('portada.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $tutorial = Tutorial::firstOrFail();

        $this->assertSame('Cómo registrar un pago', $tutorial->title);
        $this->assertSame(Tutorial::KIND_FILE, $tutorial->kind);
        $this->assertSame(3, $tutorial->sort_order);
        $this->assertSame('video', $tutorial->media_type);
        $this->assertSame('tutorial.mp4', $tutorial->original_name);
        $this->assertTrue($tutorial->is_active);

        /** @var FilesystemAdapter $disk El contrato Filesystem no declara url()/assertExists(). */
        $disk = Storage::disk(Tutorial::DISK);

        $disk->assertExists($tutorial->path);
        $disk->assertExists($tutorial->poster_path);

        // El panel oculto lista el tutorial con su URL pública ya resuelta.
        $response = $this->actingAs($this->client(), 'portal')->get($this->manageUrl());

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tutorials/Manage')
            ->where('tutorials.0.media_type', 'video')
            ->where('tutorials.0.size_human', '2 MB')
            ->where('limits.effective_mb', 300)
            ->where('locked', false)
        );

        // La URL del archivo va SIN host (ni http, ni puerto, ni dominio): así el
        // video se sirve desde el mismo servidor en el que se ve el portal
        // (localhost:8000, un vhost local o el dominio real).
        $tutorialProps = json_decode(json_encode($response->viewData('page')['props']), true)['tutorials'][0];

        $this->assertStringStartsWith('/', $tutorialProps['src']);
        $this->assertStringEndsWith('.mp4', $tutorialProps['src']);
        $this->assertStringNotContainsString('http', $tutorialProps['src']);
        $this->assertStringNotContainsString('http', (string) $tutorialProps['poster']);
    }

    public function test_admin_can_add_a_tutorial_by_url(): void
    {
        $response = $this->actingAs($this->client(), 'portal')->post($this->manageUrl(), [
            'title' => 'Manual en PDF',
            'kind' => Tutorial::KIND_LINK,
            'url' => 'https://erp-spmx.com/storage/tutoriales/manual.pdf',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $tutorial = Tutorial::firstOrFail();

        $this->assertSame('document', $tutorial->media_type);
        $this->assertNull($tutorial->path);
        $this->assertSame('https://erp-spmx.com/storage/tutoriales/manual.pdf', $tutorial->src_url);
    }

    public function test_upload_is_rejected_without_title_or_with_a_forbidden_extension(): void
    {
        $this->actingAs($this->client(), 'portal')
            ->post($this->manageUrl(), [
                'kind' => Tutorial::KIND_FILE,
                'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors(['title', 'file']);

        $this->assertSame(0, Tutorial::count());
    }

    public function test_admin_can_delete_a_tutorial_and_its_file(): void
    {
        $tutorial = Tutorial::create([
            'title' => 'Temporal',
            'kind' => Tutorial::KIND_FILE,
            'disk' => Tutorial::DISK,
            'path' => 'tutoriales/temporal.mp4',
            'mime' => 'video/mp4',
            'size' => 1024,
            'is_active' => true,
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(Tutorial::DISK);

        $disk->put($tutorial->path, 'contenido');

        // Sin sesión del portal no se puede borrar nada.
        $this->delete($this->manageUrl().'/'.$tutorial->id)->assertRedirect(route('login'));

        $this->assertDatabaseHas('tutorials', ['id' => $tutorial->id]);

        $this->actingAs($this->client(), 'portal')
            ->delete($this->manageUrl().'/'.$tutorial->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('tutorials', ['id' => $tutorial->id]);
        $disk->assertMissing('tutoriales/temporal.mp4');
    }
}
