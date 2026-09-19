<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Tutorial (video o archivo) mostrado en la sección "Tutoriales" del portal.
 *
 * El archivo vive en el disco `public` del portal (storage/app/public/tutorials)
 * y se sirve como archivo estático en /storage/tutorials/..., de modo que el
 * navegador puede pedirlo por rangos y reproducirlo sin descargarlo completo.
 *
 * NO guardar los videos dentro del proyecto (resources/ o public/build): eso
 * infla el build de Vite, el repositorio y cada despliegue.
 */
class Tutorial extends Model
{
    /** Archivo subido al portal. */
    public const KIND_FILE = 'file';

    /** Enlace externo (YouTube, Vimeo, CDN…). */
    public const KIND_LINK = 'link';

    /** Disco donde se guardan los archivos subidos. */
    public const DISK = 'public';

    /** Carpeta dentro del disco. */
    public const FOLDER = 'tutorials';

    /** Extensiones aceptadas en las subidas (video, documentos e imágenes). */
    public const FILE_EXTENSIONS = [
        'mp4', 'webm', 'ogv', 'mov', 'm4v',
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'csv',
        'jpg', 'jpeg', 'png', 'webp', 'zip',
    ];

    /** Extensiones que el navegador reproduce con <video>. */
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogv', 'ogg', 'mov', 'm4v'];

    protected $fillable = [
        'title',
        'description',
        'kind',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'poster_path',
        'url',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** Tutoriales visibles en el portal, en el orden configurado. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('created_at');
    }

    /** URL pública del archivo subido o del enlace externo. */
    public function getSrcUrlAttribute(): ?string
    {
        if ($this->kind === self::KIND_LINK) {
            return $this->url;
        }

        return $this->publicUrl($this->path);
    }

    /** URL pública de la portada (opcional). */
    public function getPosterUrlAttribute(): ?string
    {
        return $this->publicUrl($this->poster_path);
    }

    /**
     * URL del archivo SIN host (`/storage/tutorials/video.mp4`).
     *
     * A propósito: si se devolviera absoluta, se construiría con `APP_URL` y el
     * navegador pediría el video al host/puerto equivocado cada vez que APP_URL
     * no coincida con la dirección real (p. ej. portal en `localhost:8000` con
     * `php artisan serve` y `APP_URL=http://localhost` → el video se pedía al
     * puerto 80 y el servidor respondía 404). Al omitir el host, la petición va
     * siempre al mismo servidor desde el que se está viendo el portal.
     */
    private function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $url = $this->disk()->url($path);
        $relative = parse_url($url, PHP_URL_PATH);

        return $relative ?: $url;
    }

    /**
     * Cómo debe mostrarse el tutorial:
     *  - `embed`    → reproductor externo (YouTube/Vimeo) en un iframe
     *  - `video`    → reproducible con <video> (archivo propio o URL directa)
     *  - `document` → PDF/documento/imagen: se abre en otra pestaña
     */
    public function getMediaTypeAttribute(): string
    {
        if ($this->embed_url) {
            return 'embed';
        }

        return $this->isVideo() ? 'video' : 'document';
    }

    /** URL de inserción de YouTube/Vimeo (null si el enlace no es de esos sitios). */
    public function getEmbedUrlAttribute(): ?string
    {
        if ($this->kind !== self::KIND_LINK || blank($this->url)) {
            return null;
        }

        if (preg_match('~youtube\.com/(?:watch\?v=|embed/|shorts/|live/)([A-Za-z0-9_-]{6,})~', $this->url, $matches)
            || preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $this->url, $matches)) {
            return 'https://www.youtube.com/embed/'.$matches[1].'?rel=0';
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d{6,})~', $this->url, $matches)) {
            return 'https://player.vimeo.com/video/'.$matches[1];
        }

        return null;
    }

    /** ¿El contenido es un video reproducible en el navegador? */
    public function isVideo(): bool
    {
        if ($this->kind === self::KIND_LINK) {
            return in_array($this->extension(), self::VIDEO_EXTENSIONS, true);
        }

        return str_starts_with((string) $this->mime, 'video/')
            || in_array($this->extension(), self::VIDEO_EXTENSIONS, true);
    }

    /** Extensión en minúsculas del archivo subido o del enlace externo. */
    private function extension(): string
    {
        $name = $this->kind === self::KIND_LINK
            ? parse_url((string) $this->url, PHP_URL_PATH)
            : $this->path;

        return strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));
    }

    /**
     * Disco del tutorial. Se tipa como FilesystemAdapter porque el contrato
     * Filesystem no declara `url()` (aunque el adaptador sí lo implementa).
     */
    private function disk(): FilesystemAdapter
    {
        return Storage::disk($this->disk ?: self::DISK);
    }
}
