<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Notificacion;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Admin\Notificaciones\StoreNotificacionRequest;

class NotificacionesController extends Controller
{
    private function esUsuarioRolVentas(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $tipo = trim((string) ($user->tipo ?? ''));
        if (in_array($tipo, ['Ventas', 'Ventas Limitado', 'vendedor', 'Usuario Ventas'], true)) {
            return true;
        }

        // UI: "Usuario Ventas" = Spatie usuario_ventas (puede fallar hasRole por guard api vs web)
        $rolVentas = config('constants.ROL_USUARIO_VENTAS', 'usuario_ventas');

        return $user->roles()->where('name', $rolVentas)->exists();
    }

    public function index(Request $request)
    {
        $esVentas = $this->esUsuarioRolVentas();

        $notificaciones = Notificacion::query()
            ->when($esVentas, function ($q) {
                $userId = Auth::id();
                // ponytail: filtro en lectura; techo = join por notif; upgrade = id_vendedor al crear
                $q->where('tipo', 'Cuentas por cobrar')
                    ->where('referencia', 'venta')
                    ->whereExists(function ($sub) use ($userId) {
                        $sub->selectRaw('1')
                            ->from('ventas')
                            ->whereColumn('ventas.id', 'notificaciones.id_referencia')
                            ->where(function ($v) use ($userId) {
                                $v->where('ventas.id_vendedor', $userId)
                                    ->orWhereExists(function ($d) use ($userId) {
                                        $d->selectRaw('1')
                                            ->from('detalles_venta')
                                            ->whereColumn('detalles_venta.id_venta', 'ventas.id')
                                            ->where('detalles_venta.id_vendedor', $userId);
                                    });
                            });
                    });
            })
            ->when(! $esVentas && $request->tipo, function ($q) use ($request) {
                $q->where('tipo', $request->tipo);
            })
            ->when($request->referencia, function ($q) use ($request) {
                $q->where('referencia', $request->referencia);
            })
            ->when($request->categoria, function ($q) use ($request) {
                $q->where('categoria', $request->categoria);
            })
            ->when($request->leido !== null, function ($q) use ($request) {
                $q->where('leido', !! $request->leido);
            })
            ->when($request->buscador, function ($query) use ($request) {
                // Agrupar orWhere para no romper el filtro de tipo/vendedor
                $query->where(function ($q) use ($request) {
                    $q->where('titulo', 'like', '%'.$request->buscador.'%')
                        ->orWhere('descripcion', 'like', '%'.$request->buscador.'%');
                });
            })
            ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
            ->paginate($request->paginate);

        return Response()->json($notificaciones, 200);
    }

    public function read($id)
    {
        $notificacion = Notificacion::findOrFail($id);

        return Response()->json($notificacion, 200);
    }

    public function store(StoreNotificacionRequest $request)
    {
        if ($request->id) {
            $notificacion = Notificacion::findOrFail($request->id);
        } else {
            $notificacion = new Notificacion;
        }

        $notificacion->fill($request->all());
        $notificacion->save();

        return Response()->json($notificacion, 200);
    }

    public function delete($id)
    {
        $notificacion = Notificacion::findOrFail($id);
        $notificacion->delete();

        return Response()->json($notificacion, 201);
    }
}
