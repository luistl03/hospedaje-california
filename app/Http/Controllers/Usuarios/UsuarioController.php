<?php

namespace App\Http\Controllers\Usuarios;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
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
            'password' => Hash::make($request->password),
            'rol_id'   => $request->rol_id,
            'activo'   => 1,
        ]);

        return redirect()->route('usuarios.index')
            ->with('exito', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $usuario)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|unique:users,email,' . $usuario->id,
            'rol_id' => 'required|exists:roles,id',
            'activo' => 'required|in:0,1',
        ], [
            'email.unique'    => 'Este correo electrónico ya está registrado.',
            'name.required'   => 'El nombre es obligatorio.',
            'email.required'  => 'El correo es obligatorio.',
            'rol_id.required' => 'El rol es obligatorio.',
            'rol_id.exists'   => 'El rol seleccionado no es válido.',
        ]);

        $datos = [
            'name'   => $request->name,
            'email'  => $request->email,
            'rol_id' => $request->rol_id,
            'activo' => $request->activo,
        ];

        if ($request->filled('password')) {
            if ($request->password < 6) {
                return redirect()->back()
                    ->with('error', 'La contraseña debe tener al menos 6 caracteres.');
            }
            $datos['password'] = Hash::make($request->password);
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

        $usuario->delete();
        return redirect()->route('usuarios.index')
            ->with('exito', 'Usuario eliminado correctamente.');
    }
}