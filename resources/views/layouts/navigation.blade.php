<nav class="navbar navbar-expand-lg" style="background-color: var(--azul-marino);">
    <div class="container-fluid px-4">

        <!-- Logo/Marca -->
        <a class="navbar-brand" href="{{ route('inicio') }}">
            <img src="{{ asset('images/isologo_california.png') }}"
                 alt="Hospedaje California"
                 style="height: 40px;">
        </a>

        <!-- Menú derecha -->
        <div class="d-flex align-items-center gap-3">

            <!-- Nombre del usuario -->
            <span style="color: var(--dorado); font-size: 0.9rem;">
                {{ Auth::user()->name }}
            </span>

            <!-- Botón cerrar sesión -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm"
                        style="background-color: var(--dorado); color: white; border-radius: 20px; padding: 5px 16px;">
                    Cerrar Sesión
                </button>
            </form>

        </div>
    </div>
</nav>