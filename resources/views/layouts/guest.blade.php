<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar Sesión — Hospedaje California</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

{{-- body-login: centra la card en pantalla completa (flexbox) --}}
<body class="body-login">

    {{-- Figuras decorativas del fondo (círculos via ::before / ::after en CSS) --}}
    <div class="fondo-decorativo"></div>

    {{-- CARD DE LOGIN — Contenedor principal dividido en dos mitades --}}
    <div class="login-card">

        {{-- LADO IZQUIERDO — Logo con marco dorado --}}
        <div class="login-izquierdo">
            <div class="login-logo-marco">
                <img src="{{ asset('images/isologo_california.png') }}"
                     alt="Logo Hospedaje California">
            </div>
        </div>

        {{-- LADO DERECHO — Formulario (slot inyectado por Blade) --}}
        <div class="login-derecho">
            {{ $slot }}
        </div>

    </div>

</body>
</html>