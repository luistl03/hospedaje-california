<x-guest-layout>

    <div class="login-formulario">

        <!-- Encabezado -->
        <div class="login-encabezado">
            <span class="login-sistema">Sistema de Gestión</span>
            <h2>Hospedaje California</h2>
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Error general de credenciales -->
            @if ($errors->has('email'))
                <div class="login-error">
                    <i class="bi bi-exclamation-circle"></i>
                    {{ $errors->first('email') }}
                </div>
            @endif

            @if ($errors->has('password'))
                <div class="login-error">
                    <i class="bi bi-exclamation-circle"></i>
                    {{ $errors->first('password') }}
                </div>
            @endif

            <!-- Campo Correo -->
            <div class="campo-grupo">
                <label for="email">Correo electrónico</label>
                <div class="campo-input {{ $errors->has('email') ? 'error' : '' }}">
                    <i class="bi bi-envelope campo-icono"></i>
                    <input type="email" id="email" name="email"
                        value="{{ old('email') }}"
                        placeholder="tucorreo@ejemplo.com"
                        required>
                </div>
            </div>

            <!-- Campo Contraseña -->
            <div class="campo-grupo">
                <label for="password">Contraseña</label>
                <div class="campo-password {{ $errors->has('password') ? 'error' : '' }}">
                    <i class="bi bi-lock campo-icono"></i>
                    <input type="password" id="password" name="password"
                        placeholder="••••••••"
                        required>
                    <button type="button" class="btn-ojito" onclick="togglePassword('password', 'ojitoLogin')">
                        <i class="bi bi-eye" id="ojitoLogin"></i>
                    </button>
                </div>
            </div>

            <!-- Botón -->
            <button type="submit" class="btn-login">
                Iniciar Sesión
            </button>

        </form>

    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            @if ($errors->has('password'))
                document.getElementById('password').focus();
            @elseif ($errors->has('email'))
                document.getElementById('email').focus();
            @endif
        });
    </script>

</x-guest-layout>