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
<body>

    <div class="sistema-contenedor">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <!-- ENCABEZADO: Logo -->
            <div class="sidebar-logo">
                <div class="sidebar-logo-marco">
                    <img src="{{ asset('images/isologo_california.png') }}" alt="Hospedaje California">
                </div>
            </div>

            <!-- NAVEGACIÓN: Links -->
            <nav class="sidebar-nav">
                <a href="{{ route('inicio') }}" class="{{ request()->routeIs('inicio') ? 'activo' : '' }}">
                    <i class="bi bi-house"></i> Inicio
                </a>
                <a href="{{ route('habitaciones.index') }}" class="{{ request()->routeIs('habitaciones.index') ? 'activo' : '' }}">
                    <i class="bi bi-door-open"></i> Habitaciones
                </a>
                <div class="sidebar-separador"></div>
                <a href="{{ route('huespedes.index') }}" class="{{ request()->routeIs('huespedes.index') ? 'activo' : '' }}">
                    <i class="bi bi-people"></i> Huéspedes
                </a>
                <a href="{{ route('reservas.index') }}" class="{{ request()->routeIs('reservas.index') ? 'activo' : '' }}">
                    <i class="bi bi-calendar-check"></i> Reservas
                </a>
                <div class="sidebar-separador"></div>
                @if(Auth::user()->rol->nombre === 'gerente')
                    <a href="#">
                        <i class="bi bi-bar-chart"></i> Reportes
                    </a>
                    <a href="{{ route('usuarios.index') }}" class="{{ request()->routeIs('usuarios.index') ? 'activo' : '' }}">
                        <i class="bi bi-person-gear"></i> Usuarios
                    </a>
                @endif
            </nav>

            <!-- PIE: Usuario y cerrar sesión -->
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

        <!-- CONTENIDO PRINCIPAL -->
        <main class="contenido-principal">
            {{ $slot }}
        </main>

    </div>
    @stack('scripts')
</body>
</html>