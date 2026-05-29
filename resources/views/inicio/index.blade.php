<x-app-layout>

    <div class="container mt-5">
        <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
            <h4 style="color: var(--azul-marino);">
                Bienvenido, {{ Auth::user()->name }}
            </h4>
            <p class="text-muted mt-2">Has iniciado sesión correctamente en el sistema.</p>
        </div>
    </div>

</x-app-layout>