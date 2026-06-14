<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar Sesión — Hospedaje California</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Bootstrap Icons-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="body-login">
    
    <!-- Figuras decorativas del fondo -->
    <div class="fondo-decorativo"></div>

    <div class="login-card">

        <!-- LADO IZQUIERDO -->
        <div class="login-izquierdo">
            <div class="login-logo-marco">
                <img src="{{ asset('images/isologo_california.png') }}"
                     alt="Logo Hospedaje California">
            </div>
        </div>

        <!-- LADO DERECHO -->
        <div class="login-derecho">
            {{ $slot }}
        </div>

    </div>

</body>
</html>

