<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Rutas de mantenimiento por URL (para hostings sin terminal).
 *
 * No llevan llave: basta con tener la sesión del portal abierta.
 */
class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function client(): Client
    {
        return Client::create([
            'name' => 'Cliente Demo',
            'tax_id' => 'XAXX010101000',
        ]);
    }

    private function url(string $action): string
    {
        return '/mantenimiento/'.$action;
    }

    public function test_routes_require_a_portal_session(): void
    {
        $this->get($this->url('limpiar-cache'))->assertRedirect(route('login'));
        $this->get($this->url('storage-link'))->assertRedirect(route('login'));
        $this->get($this->url('tabla-tutoriales'))->assertRedirect(route('login'));
    }

    public function test_cache_route_clears_caches(): void
    {
        $response = $this->actingAs($this->client(), 'portal')->get($this->url('limpiar-cache'));

        $response->assertOk();
        $response->assertSee('php artisan optimize:clear');
    }

    public function test_storage_link_route_creates_a_working_link(): void
    {
        // Se apunta a una carpeta temporal para no tocar el enlace real del proyecto.
        $base = storage_path('framework/testing/maintenance');

        File::deleteDirectory($base);
        File::ensureDirectoryExists($base.'/app-public');

        config([
            'filesystems.links' => [
                $base.'/public-storage' => $base.'/app-public',
            ],
        ]);

        try {
            $response = $this->actingAs($this->client(), 'portal')->get($this->url('storage-link'));

            $response->assertOk();
            $response->assertSee('php artisan storage:link');
            $this->assertDirectoryExists($base.'/public-storage');

            // El enlace sirve de verdad: lo escrito en el destino se ve por el enlace.
            File::put($base.'/app-public/prueba.txt', 'ok');
            $this->assertFileExists($base.'/public-storage/prueba.txt');
        } finally {
            // En Windows `File::deleteDirectory` no puede borrar enlaces de
            // directorio (junctions), así que primero se quita el enlace con
            // `rmdir`, que elimina el enlace SIN tocar el contenido del destino.
            if (is_dir($base.'/public-storage')) {
                @rmdir($base.'/public-storage');
            }

            File::deleteDirectory($base);
        }
    }

    public function test_tutorials_migration_route_is_scoped_to_that_migration(): void
    {
        $response = $this->actingAs($this->client(), 'portal')->get($this->url('tabla-tutoriales'));

        $response->assertOk();
        // Solo corre la migración de tutoriales (nunca un `migrate` general:
        // la base de datos se comparte con el ERP).
        $response->assertSee('create_tutorials_table');
    }
}
