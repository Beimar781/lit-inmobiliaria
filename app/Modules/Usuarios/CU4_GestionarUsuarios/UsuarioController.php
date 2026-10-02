<?php

namespace App\Modules\Usuarios\CU4_GestionarUsuarios;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\BitacoraService;
use App\Services\HistorialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU4: Gestionar usuarios (solo Administrador)
 * Flujo principal:
 *  1. El administrador accede al módulo de usuarios        -> index()
 *  2. Elige registrar, editar o cambiar estado             -> crear() / editar() / cambiarEstado()
 *  3. El sistema muestra el formulario                     -> crear.blade.php / editar.blade.php
 *  4-5. Ingresa los datos (nombre, correo, rol, teléfono, estado) y confirma
 *  6. El sistema valida y guarda en la base de datos       -> guardar() / actualizar()  (validación en UsuarioRequest)
 */
class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q'));

        $usuarios = Usuario::with('rol')
            ->when($busqueda !== '', function ($consulta) use ($busqueda) {
                $consulta->where(function ($donde) use ($busqueda) {
                    $donde->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('email', 'like', "%{$busqueda}%");
                });
            })
            ->when($request->filled('idrol'), fn ($consulta) => $consulta->where('idrol', $request->query('idrol')))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('usuarios::CU4_GestionarUsuarios.index', [
            'usuarios' => $usuarios,
            'roles' => Rol::orderBy('idrol')->pluck('nombre', 'idrol'),
            'busqueda' => $busqueda,
        ]);
    }

    public function crear(): View
    {
        return view('usuarios::CU4_GestionarUsuarios.crear', [
            'usuario' => null,
            'roles' => Rol::orderBy('idrol')->pluck('nombre', 'idrol'),
        ]);
    }

    public function guardar(UsuarioRequest $request): RedirectResponse
    {
        $usuario = Usuario::create($request->validated()); // la contraseña se guarda con hash (ver modelo Usuario)

        BitacoraService::registrar('USUARIO_CREADO', 'Usuarios', "Registró al usuario {$usuario->nombre} ({$usuario->email})", [
            'idusuario' => $usuario->idusuario,
            'rol' => $usuario->rol->nombre,
            'estado' => $usuario->estado,
        ]);

        return redirect()->route('usuarios.index')->with('status', 'Usuario registrado correctamente.');
    }

    public function editar(Usuario $usuario): View
    {
        return view('usuarios::CU4_GestionarUsuarios.editar', [
            'usuario' => $usuario,
            'roles' => Rol::orderBy('idrol')->pluck('nombre', 'idrol'),
        ]);
    }

    public function actualizar(UsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        $datos = $request->validated();

        // Si deja la contraseña vacía, se conserva la actual.
        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        // Un administrador no puede quitarse a sí mismo el rol ni desactivar su propia cuenta.
        if ($usuario->is($request->user())) {
            unset($datos['idrol'], $datos['estado']);
        }

        $campos = ['nombre', 'email', 'telefono', 'idrol', 'estado'];
        $antes = $usuario->only($campos);

        $usuario->update($datos);

        [$anterior, $actual] = HistorialService::cambios($antes, $usuario->fresh()->only($campos));
        $cambioPassword = isset($datos['password']);

        if ($anterior !== [] || $cambioPassword) {
            BitacoraService::registrar('USUARIO_MODIFICADO', 'Usuarios', "Modificó al usuario {$usuario->nombre} ({$usuario->email})", [
                'antes' => $anterior,
                'despues' => $actual,
                'contrasena_cambiada' => $cambioPassword,
            ]);
        }

        return redirect()->route('usuarios.index')->with('status', 'Usuario actualizado correctamente.');
    }

    /** Activa o desactiva la cuenta (baja lógica: el usuario no se borra). */
    public function cambiarEstado(Request $request, Usuario $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $usuario->estado = $usuario->estaActivo() ? Usuario::INACTIVO : Usuario::ACTIVO;
        $usuario->save();

        BitacoraService::registrar(
            $usuario->estaActivo() ? 'USUARIO_ACTIVADO' : 'USUARIO_DESACTIVADO',
            'Usuarios',
            ($usuario->estaActivo() ? 'Activó' : 'Desactivó') . " al usuario {$usuario->nombre} ({$usuario->email})"
        );

        return back()->with('status', 'El usuario ahora está ' . strtolower($usuario->estado) . '.');
    }
}
