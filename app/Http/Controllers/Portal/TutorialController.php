<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tutorial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tutoriales del portal.
 *
 *  - `index()`   → sección VISIBLE "Tutoriales" del menú lateral (todos los clientes).
 *  - `manage()`  → panel de gestión OCULTO: no hay ningún enlace en la interfaz,
 *                  solo se entra escribiendo `/gestion-tutoriales`.
 *  - `store()` / `destroy()` → agregar y quitar tutoriales desde ese panel.
 *
 * Protección opcional: si en el .env se define `TUTORIALS_ADMIN_KEY`, el panel
 * pide la llave una sola vez (`/gestion-tutoriales?llave=LA_LLAVE`) y la
 * recuerda en la sesión; sin ella responde 404.
 */
class TutorialController extends Controller
{
    /** Marca en sesión de que el panel ya se autorizó con la llave. */
    private const SESSION_KEY = 'tutorials_admin';

    /** Sección de tutoriales visible para los clientes. */
    public function index(): Response
    {
        return Inertia::render('Tutorials/Index', [
            'tutorials' => Tutorial::visible()
                ->get()
                ->map(fn (Tutorial $tutorial) => $this->payload($tutorial))
                ->all(),
        ]);
    }

    /** Panel de gestión (oculto, sin enlace en la interfaz). */
    public function manage(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('Tutorials/Manage', [
            'tutorials' => Tutorial::orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Tutorial $tutorial) => $this->payload($tutorial))
                ->all(),
            'limits' => $this->uploadLimits(),
            'acceptedExtensions' => Tutorial::FILE_EXTENSIONS,
            'locked' => $this->keyIsRequired(),
            // El servidor redirige aquí con `?error=size` cuando PHP rechazó la
            // subida por tamaño (ver bootstrap/app.php).
            'oversized' => $request->query('error') === 'size',
        ]);
    }

    /** Agrega un tutorial: archivo subido o enlace externo. */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $maxKb = max(1, $this->uploadLimits()['effective_mb']) * 1024;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'kind' => ['required', 'in:'.Tutorial::KIND_FILE.','.Tutorial::KIND_LINK],
            'url' => ['nullable', 'required_if:kind,'.Tutorial::KIND_LINK, 'url', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'file' => [
                'nullable',
                'required_if:kind,'.Tutorial::KIND_FILE,
                'file',
                'mimes:'.implode(',', Tutorial::FILE_EXTENSIONS),
                'max:'.$maxKb,
            ],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $attributes = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'kind' => $validated['kind'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => true,
        ];

        if ($validated['kind'] === Tutorial::KIND_LINK) {
            $attributes['url'] = $validated['url'];
        } else {
            /** @var UploadedFile $file */
            $file = $request->file('file');
            $disk = Tutorial::DISK;

            $attributes['disk'] = $disk;
            $attributes['path'] = $file->storeAs(Tutorial::FOLDER, $this->fileName($file), $disk);
            $attributes['original_name'] = $file->getClientOriginalName();
            $attributes['mime'] = $file->getClientMimeType();
            $attributes['size'] = $file->getSize();
        }

        // La portada es opcional: sirve para mostrar la tarjeta sin descargar el video.
        if ($request->hasFile('poster')) {
            $attributes['poster_path'] = $request->file('poster')->storeAs(
                Tutorial::FOLDER.'/posters',
                $this->fileName($request->file('poster')),
                Tutorial::DISK
            );
        }

        Tutorial::create($attributes);

        return back()->with('success', 'Tutorial agregado. Ya se muestra en la sección "Tutoriales".');
    }

    /** Elimina un tutorial y su archivo (y su portada, si tiene). */
    public function destroy(Request $request, Tutorial $tutorial): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $disk = Storage::disk($tutorial->disk ?: Tutorial::DISK);

        if ($tutorial->path) {
            $disk->delete($tutorial->path);
        }

        if ($tutorial->poster_path) {
            $disk->delete($tutorial->poster_path);
        }

        $tutorial->delete();

        return back()->with('success', 'Tutorial eliminado.');
    }

    /**
     * Control de acceso del panel oculto.
     *
     *  - Sin llave configurada: se entra solo con la URL (protección por
     *    oscuridad; la ruta no está enlazada en ninguna pantalla).
     *  - Con `TUTORIALS_ADMIN_KEY`: se pide UNA vez
     *    (`/gestion-tutoriales?llave=LA_LLAVE`) y queda autorizado en la sesión.
     */
    private function authorizeAdmin(Request $request): void
    {
        $expected = (string) config('portal.tutorials_admin_key');

        if ($expected === '') {
            return;
        }

        if ($request->filled('llave') && hash_equals($expected, (string) $request->query('llave'))) {
            $request->session()->put(self::SESSION_KEY, true);

            return;
        }

        abort_unless($request->session()->get(self::SESSION_KEY) === true, 404);
    }

    /** ¿El panel está protegido con llave? (para avisarlo en la interfaz) */
    private function keyIsRequired(): bool
    {
        return (string) config('portal.tutorials_admin_key') !== '';
    }

    /**
     * Cuando el archivo supera `post_max_size`, PHP descarta el POST completo y
     * Laravel responde 413 (ver el render de `PostTooLargeException` en
     * bootstrap/app.php, que devuelve al panel con `?error=size`). En el
     * navegador, además, se valida el peso antes de subir nada.
     */

    /** Nombre del archivo guardado: legible + sufijo aleatorio, para que al
     * reemplazar un video cambie la URL (y el navegador no sirva caché viejo).
     */
    private function fileName(UploadedFile $file): string
    {
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'tutorial';
        $extension = strtolower($file->getClientOriginalExtension() ?: 'dat');

        return Str::limit($base, 60, '').'-'.Str::lower(Str::random(6)).'.'.$extension;
    }

    /** Datos que consume el frontend. */
    private function payload(Tutorial $tutorial): array
    {
        return [
            'id' => $tutorial->id,
            'title' => $tutorial->title,
            'description' => $tutorial->description,
            'kind' => $tutorial->kind,
            'media_type' => $tutorial->media_type,
            'src' => $tutorial->src_url,
            'embed' => $tutorial->embed_url,
            'poster' => $tutorial->poster_url,
            'original_name' => $tutorial->original_name,
            'size' => $tutorial->size,
            'size_human' => $this->humanSize($tutorial->size),
            'is_active' => (bool) $tutorial->is_active,
            'sort_order' => (int) $tutorial->sort_order,
            'created_at' => $tutorial->created_at?->format('d/m/Y H:i'),
        ];
    }

    private function humanSize(?int $bytes): ?string
    {
        if (! $bytes) {
            return null;
        }

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }

    /**
     * Límites reales de subida: el menor entre lo configurado en el portal y lo
     * que permite php.ini. Se muestran en el panel para saber por qué un archivo
     * pesado no se puede subir.
     */
    private function uploadLimits(): array
    {
        $configured = (int) config('portal.tutorials_max_upload_mb', 512);
        $phpUpload = $this->iniMegabytes('upload_max_filesize');
        $phpPost = $this->iniMegabytes('post_max_size');

        $candidates = array_filter([$configured, $phpUpload, $phpPost]);
        $effective = $candidates ? min($candidates) : $configured;

        return [
            'configured_mb' => $configured,
            'php_upload_mb' => $phpUpload,
            'php_post_mb' => $phpPost,
            'effective_mb' => max(1, (int) $effective),
        ];
    }

    /** Convierte un valor de php.ini ("300M") a bytes (0 = sin límite). */
    private function iniBytes(string $option): int
    {
        $value = trim((string) ini_get($option));

        if ($value === '' || $value === '-1' || (int) $value === 0) {
            return 0;
        }

        $bytes = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1048576,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }

    /** Convierte un valor de php.ini a megabytes (0 = sin límite). */
    private function iniMegabytes(string $option): int
    {
        $bytes = $this->iniBytes($option);

        return $bytes > 0 ? max(1, (int) round($bytes / 1048576)) : 0;
    }
}
