<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait HasOffloadedDte
{
    /** @var array|null|false */
    protected $resolvedDteMainCache = false;

    /** @var array|null|false */
    protected $resolvedDteInvalidacionCache = false;

    public function getDteAttribute($value)
    {
        $raw = $this->attributes['dte'] ?? null;
        if ($raw !== null && $raw !== '') {
            return is_string($raw) ? json_decode($raw, true) : $raw;
        }

        $s3Key = $this->attributes['dte_s3_key'] ?? null;
        if (!empty($s3Key)) {
            if ($this->resolvedDteMainCache === false) {
                $this->resolvedDteMainCache = $this->fetchDteJsonFromS3($s3Key);
            }

            return $this->resolvedDteMainCache;
        }

        return null;
    }

    public function getDteInvalidacionAttribute($value)
    {
        $raw = $this->attributes['dte_invalidacion'] ?? null;
        if ($raw !== null && $raw !== '') {
            return is_string($raw) ? json_decode($raw, true) : $raw;
        }

        $s3Key = $this->attributes['dte_invalidacion_s3_key'] ?? null;
        if (!empty($s3Key)) {
            if ($this->resolvedDteInvalidacionCache === false) {
                $this->resolvedDteInvalidacionCache = $this->fetchDteJsonFromS3($s3Key);
            }

            return $this->resolvedDteInvalidacionCache;
        }

        return null;
    }

    /**
     * Indica si el JSON principal del DTE está en S3 (columna local vaciada).
     */
    public function getDteEnS3Attribute(): bool
    {
        return !empty($this->attributes['dte_s3_key'] ?? null)
            && ($this->attributes['dte'] ?? null) === null;
    }

    /**
     * Indica si el JSON de invalidación está en S3.
     */
    public function getDteInvalidacionEnS3Attribute(): bool
    {
        return !empty($this->attributes['dte_invalidacion_s3_key'] ?? null)
            && ($this->attributes['dte_invalidacion'] ?? null) === null;
    }

    public function scopeWhereHasDtePayload(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($table) {
            $q->whereNotNull("{$table}.dte")
                ->orWhereNotNull("{$table}.dte_s3_key");
        });
    }

    public function temporaryDteDownloadUrl(int $minutes = null): ?string
    {
        $key = $this->attributes['dte_s3_key'] ?? null;
        if (empty($key)) {
            return null;
        }
        $minutes = $minutes ?? (int) config('dte.presigned_minutes', 30);
        $disk = config('dte.disk', 's3');

        return Storage::disk($disk)->temporaryUrl($key, now()->addMinutes($minutes));
    }

    public function temporaryDteInvalidacionDownloadUrl(int $minutes = null): ?string
    {
        $key = $this->attributes['dte_invalidacion_s3_key'] ?? null;
        if (empty($key)) {
            return null;
        }
        $minutes = $minutes ?? (int) config('dte.presigned_minutes', 30);
        $disk = config('dte.disk', 's3');

        return Storage::disk($disk)->temporaryUrl($key, now()->addMinutes($minutes));
    }

    public function refresh()
    {
        $this->resolvedDteMainCache = false;
        $this->resolvedDteInvalidacionCache = false;

        return parent::refresh();
    }

    protected function fetchDteJsonFromS3(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }
        $disk = config('dte.disk', 's3');
        try {
            if (!Storage::disk($disk)->exists($key)) {
                Log::warning('DTE S3: objeto no encontrado', ['key' => $key, 'disk' => $disk]);

                return null;
            }
            $raw = Storage::disk($disk)->get($key);
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::error('DTE S3: error al leer', ['key' => $key, 'message' => $e->getMessage()]);

            return null;
        }
    }
}
