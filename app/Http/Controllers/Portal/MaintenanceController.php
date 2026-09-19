<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Throwable;

/**
 * Tareas de mantenimiento por URL, pensadas para servidores sin terminal
 * (hosting compartido tipo HostGator).
 *
 * Bastan la sesión del portal (no hay llave en la URL):
 *
 *   /mantenimiento/limpiar-cache
 *   /mantenimiento/storage-link
 *   /mantenimiento/tabla-tutoriales
 */
class MaintenanceController extends Controller
{
    /**
     * Migración que crea ÚNICAMENTE la tabla de tutoriales.
     *
     * A propósito NO se ofrece un "migrate" general: la base de datos es
     * compartida con el ERP y sus migraciones están desincronizadas, por lo que
     * correr todas podría alterar tablas del ERP.
     */
    private const TUTORIALS_MIGRATION = '2026_09_19_000000_create_tutorials_table.php';

    /** Limpia todas las cachés: config, rutas, vistas compiladas y eventos. */
    public function clearCache(): View
    {
        return $this->report('Limpiar cachés', [
            $this->runCommand('optimize:clear'),
        ]);
    }

    /** Crea (o repara) el enlace public/storage → storage/app/public. */
    public function storageLink(): View
    {
        $results = [$this->runCommand('storage:link')];

        $works = $this->storageLinkWorks();

        // Si el enlace existe pero está roto o apunta a otro sitio, se rehace.
        if (! $works) {
            $results[] = $this->runCommand('storage:link', ['--force' => true]);
            $works = $this->storageLinkWorks();
        }

        [$link, $target] = $this->linkPair();

        return $this->report('Enlace de storage', $results, [
            'El enlace existe y apunta al storage público' => $works,
            'Carpeta destino ('.$target.') escribible' => is_dir((string) $target) && is_writable((string) $target),
            'Enlace configurado: '.($link ?: '(ninguno)') => (bool) $link,
        ]);
    }

    /** Crea solo la tabla `tutorials` (no toca ninguna otra migración). */
    public function migrateTutorials(): View
    {
        $path = 'database/migrations/'.self::TUTORIALS_MIGRATION;

        $results = [
            $this->runCommand('migrate', ['--path' => $path, '--force' => true]),
        ];

        return $this->report('Tabla de tutoriales', $results, [
            'La migración existe en el proyecto' => File::exists(base_path($path)),
            'La tabla `tutorials` ya existe' => \Illuminate\Support\Facades\Schema::hasTable('tutorials'),
        ]);
    }

    /**
     * Ejecuta un comando de Artisan sin dejar que una excepción rompa la página
     * (por ejemplo, `storage:link` lanza error si el enlace ya existe).
     *
     * @param  array<string, mixed>  $parameters
     * @return array{command: string, ok: bool, output: string}
     */
    private function runCommand(string $command, array $parameters = []): array
    {
        $line = $this->commandLine($command, $parameters);

        try {
            $exitCode = Artisan::call($command, $parameters);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            return ['command' => $line, 'ok' => false, 'output' => $e->getMessage()];
        }

        return [
            'command' => $line,
            'ok' => $exitCode === 0,
            'output' => $output !== '' ? $output : '(el comando no devolvió texto)',
        ];
    }

    /** Representación legible del comando ejecutado. */
    private function commandLine(string $command, array $parameters): string
    {
        $parts = ['php artisan', $command];

        foreach ($parameters as $key => $value) {
            if (is_string($key)) {
                $parts[] = $value === true ? $key : $key.'='.$value;
            } else {
                $parts[] = (string) $value;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Enlace y destino según `config('filesystems.links')` (así el módulo
     * funciona igual en local y en producción).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function linkPair(): array
    {
        $links = (array) config('filesystems.links', []);
        $link = array_key_first($links) ?: null;

        return [$link, $link ? ($links[$link] ?? null) : null];
    }

    /**
     * Comprueba de verdad que el enlace sirve: escribe un archivo por el destino
     * y lo busca a través del enlace.
     */
    private function storageLinkWorks(): bool
    {
        [$link, $target] = $this->linkPair();

        if (! $link || ! $target) {
            return false;
        }

        $probe = 'storage-check-'.uniqid().'.txt';

        try {
            File::ensureDirectoryExists($target);
            File::put($target.'/'.$probe, 'ok');
            $works = File::exists($link.'/'.$probe);
        } catch (Throwable $e) {
            $works = false;
        }

        File::delete($target.'/'.$probe);

        return $works;
    }

    /**
     * Página de resultado: qué se ejecutó, la salida real del comando y las
     * comprobaciones posteriores.
     *
     * @param  array<int, array{command: string, ok: bool, output: string}>  $results
     * @param  array<string, bool>  $checks
     */
    private function report(string $title, array $results, array $checks = []): View
    {
        return view('maintenance', [
            'title' => $title,
            'results' => $results,
            'checks' => $checks,
            'ranAt' => now()->format('d/m/Y H:i:s'),
        ]);
    }
}
