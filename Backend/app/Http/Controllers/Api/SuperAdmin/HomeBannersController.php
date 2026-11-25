<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use App\Services\HomeBannerImageStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomeBannersController extends Controller
{
    public function __construct(
        private HomeBannerImageStorage $imageStorage
    ) {
    }

    public function index(Request $request)
    {
        $query = HomeBanner::query();

        if ($request->filled('buscador')) {
            $buscador = $request->buscador;
            $query->where('nombre', 'like', "%{$buscador}%");
        }

        if ($request->estado !== null && $request->estado !== '') {
            $query->where('activo', $request->estado == '1');
        }

        if ($request->filled('pais')) {
            $pais = strtoupper((string) $request->pais);
            if (in_array($pais, HomeBanner::PAISES_PERMITIDOS, true)) {
                $query->where(function ($q) use ($pais) {
                    $q->whereJsonLength('paises', 0)
                        ->orWhereJsonContains('paises', $pais);
                });
            }
        }

        $ordenes = ['nombre', 'orden', 'activo', 'created_at'];
        $orden = in_array($request->get('orden'), $ordenes, true) ? $request->get('orden') : 'orden';
        $direccion = $request->get('direccion') === 'desc' ? 'desc' : 'asc';
        $paginate = min(max((int) $request->get('paginate', 10), 1), 100);

        return response()->json(
            $query->orderBy($orden, $direccion)->orderBy('id')->paginate($paginate),
            200
        );
    }

    public function store(Request $request)
    {
        $request->merge([
            'nombre' => trim((string) $request->nombre),
            'paises' => $this->parsePaisesInput($request->input('paises')),
        ]);

        $isUpdate = (int) $request->input('id') > 0;
        $rules = [
            'id' => ['nullable', 'integer', 'exists:home_banners,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'paises' => ['nullable', 'array'],
            'paises.*' => [Rule::in(HomeBanner::PAISES_PERMITIDOS)],
            'url_destino' => ['nullable', 'string', 'max:2048'],
            'abrir_nueva_pestana' => ['boolean'],
            'segundos_visualizacion' => ['integer', 'min:2', 'max:120'],
            'orden' => ['integer', 'min:0', 'max:9999'],
            'activo' => ['boolean'],
            'publicado_desde' => ['nullable', 'date'],
            'publicado_hasta' => ['nullable', 'date', 'after_or_equal:publicado_desde'],
            'imagen_desktop' => [$isUpdate ? 'nullable' : 'required', 'file', 'image', 'max:5120'],
            'imagen_mobile' => ['nullable', 'file', 'image', 'max:5120'],
        ];

        $data = $request->validate($rules, [
            'nombre.required' => 'El nombre es requerido.',
            'imagen_desktop.required' => 'La imagen de escritorio es requerida.',
        ]);

        $banner = !empty($data['id'])
            ? HomeBanner::findOrFail($data['id'])
            : new HomeBanner;

        if ($request->hasFile('imagen_desktop')) {
            $this->imageStorage->deleteIfStored($banner->imagen_desktop);
            $banner->imagen_desktop = $this->imageStorage->store($request->file('imagen_desktop'), 'desktop');
        } elseif (!$banner->exists) {
            return response()->json(['message' => 'La imagen de escritorio es requerida.'], 422);
        }

        if ($request->hasFile('imagen_mobile')) {
            $this->imageStorage->deleteIfStored($banner->imagen_mobile);
            $banner->imagen_mobile = $this->imageStorage->store($request->file('imagen_mobile'), 'mobile');
        }

        $url = trim((string) ($data['url_destino'] ?? ''));
        $banner->fill([
            'nombre' => $data['nombre'],
            'paises' => HomeBanner::normalizarPaises($data['paises'] ?? []),
            'url_destino' => $url === '' ? null : $url,
            'abrir_nueva_pestana' => $request->boolean('abrir_nueva_pestana', true),
            'segundos_visualizacion' => (int) ($data['segundos_visualizacion'] ?? $request->input('segundos_visualizacion', 5)),
            'orden' => (int) ($data['orden'] ?? $request->input('orden', 0)),
            'activo' => $request->boolean('activo', true),
            'publicado_desde' => $data['publicado_desde'] ?? null,
            'publicado_hasta' => $data['publicado_hasta'] ?? null,
        ]);
        $banner->save();

        return response()->json($banner->fresh(), 200);
    }

    public function delete($id)
    {
        $banner = HomeBanner::findOrFail($id);
        $this->imageStorage->deleteIfStored($banner->imagen_desktop);
        $this->imageStorage->deleteIfStored($banner->imagen_mobile);
        $banner->delete();

        return response()->json($banner, 200);
    }

    /**
     * @return array<int, string>|null
     */
    private function parsePaisesInput(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}