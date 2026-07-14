<?php

namespace App\Http\Controllers\Usuarios;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    // true si el usuario tiene reservas registradas a su nombre (reservas.usuario_id), sin importar el estado.
    private function tieneHistorialReservas(int $usuarioId): bool
    {
        return DB::table('reservas')
            ->where('usuario_id', $usuarioId)
            ->exists();
    }

    // true si el usuario es actualmente gerente Y activo.
    private function esGerenteActivo(User $usuario): bool
    {
        return $usuario->activo && $usuario->rol->nombre === 'gerente';
    }

    // Cuenta cuántos gerentes activos hay en el sistema, excluyendo opcionalmente a un usuario.
    private function contarGerentesActivos(?int $excluirUsuarioId = null): int
    {
        $idRolGerente = Rol::where('nombre', 'gerente')->value('id');

        return User::where('rol_id', $idRolGerente)
            ->where('activo', 1)
            ->when($excluirUsuarioId, fn($q) => $q->where('id', '!=', $excluirUsuarioId))
            ->count();
    }

    public function index()
    {
        $usuarios = User::with('rol')->get();
        $roles = Rol::all();
        return view('usuarios.index', compact('usuarios', 'roles'));
    }

    public function verificarEmail(Request $request)
    {
        $existe = User::where('email', $request->email)
            ->where('id', '!=', $request->id ?? 0)
            ->exists();

        return response()->json(['existe' => $existe]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'rol_id' => 'required|exists:roles,id',
        ], [
            'email.unique'    => 'Este correo electrónico ya está registrado.',
            'password.min'    => 'La contraseña debe tener al menos 6 caracteres.',
            'name.required'   => 'El nombre es obligatorio.',
            'email.required'  => 'El correo es obligatorio.',
            'rol_id.required' => 'El rol es obligatorio.',
            'rol_id.exists'   => 'El rol seleccionado no es válido.',
        ]);

        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
            'rol_id'   => $request->rol_id,
            'activo'   => 1,
        ]);

        return redirect()->route('usuarios.index')
            ->with('exito', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $usuario)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $usuario->id,
            'rol_id'   => 'required|exists:roles,id',
            'activo'   => 'required|in:0,1',
            'password' => 'nullable|min:6',
        ], [
            'email.unique'    => 'Este correo electrónico ya está registrado.',
            'name.required'   => 'El nombre es obligatorio.',
            'email.required'  => 'El correo es obligatorio.',
            'rol_id.required' => 'El rol es obligatorio.',
            'rol_id.exists'   => 'El rol seleccionado no es válido.',
            'password.min'    => 'La contraseña debe tener al menos 6 caracteres.',
        ]);

        $esUnoMismo = $usuario->id === auth()->id();

        $nuevoRol       = Rol::findOrFail($request->rol_id);
        $seguiraGerente = (bool) $request->activo && $nuevoRol->nombre === 'gerente';

        if ($esUnoMismo && $this->esGerenteActivo($usuario) && !$seguiraGerente) {
            return redirect()->route('usuarios.index')
                ->with('error', 'No puedes desactivarte ni cambiar tu propio rol de gerente. Pídele a otro gerente que lo haga.');
        }

        if ($this->esGerenteActivo($usuario) && !$seguiraGerente) {
            $gerentesActivosRestantes = $this->contarGerentesActivos($usuario->id);

            if ($gerentesActivosRestantes === 0) {
                return redirect()->route('usuarios.index')
                    ->with('error', 'No se puede desactivar ni cambiar el rol: es el único gerente activo del sistema. Active o cree otro gerente antes de continuar.');
            }
        }

        $datos = [
            'name'   => $request->name,
            'email'  => $request->email,
            'rol_id' => $request->rol_id,
            'activo' => $request->activo,
        ];

        if ($request->filled('password')) {
            $datos['password'] = $request->password;
        }

        $usuario->update($datos);

        return redirect()->route('usuarios.index')
            ->with('exito', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return redirect()->route('usuarios.index')
                ->with('error', 'No puedes eliminar tu propio usuario.');
        }

        if ($this->esGerenteActivo($usuario)) {
            $gerentesActivosRestantes = $this->contarGerentesActivos($usuario->id);

            if ($gerentesActivosRestantes === 0) {
                return redirect()->route('usuarios.index')
                    ->with('error', 'No se puede eliminar: es el único gerente activo del sistema. Active o cree otro gerente antes de continuar.');
            }
        }

        if ($this->tieneHistorialReservas($usuario->id)) {
            return redirect()->route('usuarios.index')
                ->with('error', 'No se puede eliminar: el usuario tiene reservas registradas a su nombre. Puede desactivarlo en su lugar.');
        }

        $usuario->delete();
        return redirect()->route('usuarios.index')
            ->with('exito', 'Usuario eliminado correctamente.');
    }
}