<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManagerStatic as Image;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class UsuarioService
{
    /**
     * Lista usuarios con filtros
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listarUsuarios(array $filters)
    {
        $user = JWTAuth::parseToken()->authenticate();
        
        $query = User::where('id_empresa', $user->id_empresa)
            ->with('sucursal', 'roles', 'roles.permissions');

        if (isset($filters['estado']) && $filters['estado'] !== null) {
            $query->where('enable', (bool)$filters['estado']);
        }

        if (isset($filters['id_sucursal']) && $filters['id_sucursal']) {
            $query->where('id_sucursal', $filters['id_sucursal']);
        }

        if (isset($filters['buscador']) && $filters['buscador']) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['buscador'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['buscador'] . '%')
                    ->orWhereHas('sucursal', function ($sq) use ($filters) {
                        $sq->where('nombre', 'like', '%' . $filters['buscador'] . '%');
                    });
            });
        }

        $orden = $filters['orden'] ?? 'id';
        $direccion = $filters['direccion'] ?? 'desc';
        $paginate = $filters['paginate'] ?? 15;

        return $query->orderBy($orden, $direccion)->paginate($paginate);
    }

    /**
     * Lista usuarios activos según tipo de usuario
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listarUsuariosActivos()
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->tipo == 'Administrador') {
            return User::where('id_empresa', $user->id_empresa)
                ->where('enable', true)
                ->orderBy('name', 'asc')
                ->get();
        } else {
            return User::where('id_sucursal', $user->id_sucursal)
                ->where('enable', true)
                ->orderBy('name', 'asc')
                ->get();
        }
    }

    /**
     * Lista usuarios activos por sucursal
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listarUsuariosActivosPorSucursal()
    {
        $user = JWTAuth::parseToken()->authenticate();

        return User::where('id_sucursal', $user->id_sucursal)
            ->where('enable', true)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Busca usuarios por texto
     *
     * @param string $texto
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function buscarUsuarios(string $texto)
    {
        $user = JWTAuth::parseToken()->authenticate();

        return User::where('id_empresa', $user->id_empresa)
            ->where('id_sucursal', $user->id_sucursal)
            ->where('name', 'like', '%' . $texto . '%')
            ->paginate(15);
    }

    /**
     * Filtra usuarios
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function filtrarUsuarios(array $filters)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $query = User::where('id_sucursal', $user->id_sucursal);

        if (isset($filters['sucursal_id']) && $filters['sucursal_id']) {
            $query->where('sucursal_id', $filters['sucursal_id']);
        }

        if (isset($filters['tipo']) && $filters['tipo']) {
            $query->where('tipo', $filters['tipo']);
        }

        return $query->orderBy('id', 'desc')->paginate(100000);
    }

    /**
     * Crea o actualiza un usuario
     *
     * @param array $data
     * @return User
     */
    public function crearOActualizarUsuario(array $data): User
    {
        if (isset($data['id']) && $data['id']) {
            $usuario = User::findOrFail($data['id']);
        } else {
            $usuario = new User();
        }

        // Procesar contraseña si viene
        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        }

        // Procesar avatar si viene
        if (isset($data['file']) && $data['file']) {
            $data['avatar'] = $this->procesarAvatar($data['file'], $usuario->avatar ?? null);
            unset($data['file']);
        }

        $usuario->fill($data);
        $usuario->save();

        // Enviar bienvenida si es nuevo usuario
        if (!isset($data['id']) || !$data['id']) {
            $usuario->bienvenida();
        }

        // Sincronizar roles
        if (isset($data['rol_id']) && $data['rol_id']) {
            $usuario->roles()->sync([$data['rol_id']]);
        }

        $usuario->load('roles');

        return $usuario;
    }

    /**
     * Procesa y guarda avatar de usuario
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string|null $avatarAnterior
     * @return string
     */
    public function procesarAvatar($file, ?string $avatarAnterior = null): string
    {
        // Eliminar avatar anterior si existe
        if ($avatarAnterior && $avatarAnterior != 'usuarios/default.jpg') {
            Storage::delete($avatarAnterior);
        }

        // Redimensionar y guardar
        $resize = Image::make($file)->resize(350, 350)->encode('jpg', 75);
        $hash = md5($resize->__toString());
        $path = "usuarios/{$hash}.jpg";
        $resize->save(public_path('img/' . $path), 50);

        return "/" . $path;
    }

    /**
     * Actualiza información de usuario
     *
     * @param int $id
     * @param array $data
     * @return User
     */
    public function actualizarInformacion(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->fill($data);
        $user->save();
        return $user;
    }

    /**
     * Actualiza email de usuario
     *
     * @param int $id
     * @param string $email
     * @return User
     */
    public function actualizarEmail(int $id, string $email): User
    {
        $user = User::findOrFail($id);
        $user->email = $email;
        $user->save();
        return $user;
    }

    /**
     * Actualiza contraseña de usuario
     *
     * @param int $id
     * @param string $password
     * @return User
     */
    public function actualizarPassword(int $id, string $password): User
    {
        $user = User::findOrFail($id);
        $user->password = Hash::make($password);
        $user->save();
        return $user;
    }

    /**
     * Actualiza código de autorización
     *
     * @param int $id
     * @param string $codigoAutorizacion
     * @return User
     */
    public function actualizarCodigoAutorizacion(int $id, string $codigoAutorizacion): User
    {
        $user = User::findOrFail($id);
        $user->codigo_autorizacion = $codigoAutorizacion;
        $user->save();
        return $user;
    }

    /**
     * Actualiza avatar de usuario
     *
     * @param int $id
     * @param \Illuminate\Http\UploadedFile $file
     * @return User
     */
    public function actualizarAvatar(int $id, $file): User
    {
        $user = User::findOrFail($id);
        $user->avatar = $this->procesarAvatar($file, $user->avatar);
        $user->save();
        return $user;
    }

    /**
     * Elimina un usuario
     *
     * @param int $id
     * @return User
     */
    public function eliminarUsuario(int $id): User
    {
        $usuario = User::findOrFail($id);
        $usuario->delete();
        return $usuario;
    }

    /**
     * Obtiene usuarios por caja
     *
     * @param int $cajaId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerUsuariosPorCaja(int $cajaId)
    {
        return User::where('caja_id', $cajaId)->get();
    }

    /**
     * Valida código de supervisor
     *
     * @param string $codigo
     * @return User|null
     */
    public function validarCodigoSupervisor(string $codigo): ?User
    {
        return User::where('codigo', $codigo)->first();
    }

    /**
     * Autentica usuario por username y password
     *
     * @param string $username
     * @param string $password
     * @return User|null
     */
    public function autenticarUsuario(string $username, string $password): ?User
    {
        $user = User::where('username', $username)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Obtiene un usuario por ID
     *
     * @param int $id
     * @return User
     */
    public function obtenerUsuario(int $id): User
    {
        return User::with('roles')->findOrFail($id);
    }
}
