<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hospedaje California</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>


{{-- VERIFICACIÓN AUTOMÁTICA DE HABITACIONES PRÓXIMAS --}}
<script>
    (function () {
        function verificarHabitacionesReservadas() {
            fetch('{{ route('sistema.verificarReservadas') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            }).catch(() => {});
        }

        // Ejecuta una vez al cargar cualquier página del sistema
        verificarHabitacionesReservadas();

        // Y luego cada 15 minutos mientras la pestaña esté abierta
        setInterval(verificarHabitacionesReservadas, 15 * 60 * 1000);
    })();
</script>
<body>

    {{-- CONTENEDOR SISTEMA — Flex horizontal: sidebar fijo + contenido. --}}
    <div class="sistema-contenedor">

        {{-- SIDEBAR — Menú lateral fijo. La clase .activo en cada link la aplica Blade con request()->routeIs(). --}}
        <aside class="sidebar">

            {{-- Logo con marco dorado --}}
            <div class="sidebar-logo">
                <div class="sidebar-logo-marco">
                    <img src="{{ asset('images/isologo_california.png') }}" alt="Hospedaje California">
                </div>
            </div>

            {{-- Navegación principal --}}
            <nav class="sidebar-nav">

                <a href="{{ route('inicio') }}"
                   class="{{ request()->routeIs('inicio') ? 'activo' : '' }}">
                    <i class="bi bi-house"></i> Inicio
                </a>

                <a href="{{ route('habitaciones.index') }}"
                   class="{{ request()->routeIs('habitaciones.index') ? 'activo' : '' }}">
                    <i class="bi bi-door-open"></i> Habitaciones
                </a>

                <div class="sidebar-separador"></div>

                <a href="{{ route('huespedes.index') }}"
                   class="{{ request()->routeIs('huespedes.index') ? 'activo' : '' }}">
                    <i class="bi bi-people"></i> Huéspedes
                </a>

                <a href="{{ route('reservas.index') }}"
                   class="{{ request()->routeIs('reservas.index') ? 'activo' : '' }}">
                    <i class="bi bi-calendar-check"></i> Reservas
                </a>

                {{-- Solo visible para gerente --}}
                @if(Auth::user()->rol->nombre === 'gerente')
                    <div class="sidebar-separador"></div>

                    <a href="{{ route('reportes.index') }}"
                    class="{{ request()->routeIs('reportes.index') ? 'activo' : '' }}">
                        <i class="bi bi-bar-chart"></i> Reportes
                    </a>

                    <a href="{{ route('predicciones.index') }}"
                    class="{{ request()->routeIs('predicciones.index') ? 'activo' : '' }}">
                        <i class="bi bi-graph-up-arrow"></i> Predicciones
                    </a>

                    <a href="{{ route('usuarios.index') }}"
                    class="{{ request()->routeIs('usuarios.index') ? 'activo' : '' }}">
                        <i class="bi bi-person-gear"></i> Usuarios
                    </a>
                @endif

            </nav>

            {{-- Pie: info del usuario autenticado y logout --}}
            <div class="sidebar-pie">
                <div class="sidebar-usuario">
                    <span class="sidebar-usuario-nombre">{{ Auth::user()->name }}</span>
                    <span class="sidebar-usuario-rol">{{ strtoupper(Auth::user()->rol->nombre) }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Cerrar Sesión</button>
                </form>
            </div>

        </aside>

        {{-- CONTENIDO PRINCIPAL — Slot donde cada página inyecta su contenido. --}}
        <main class="contenido-principal">
            {{ $slot }}
        </main>

    </div>

    {{-- Scripts de módulos JS cargados por cada vista --}}
    @stack('scripts')

</body>
</html>