<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','Inicio') · Vida Nuevamente Segura</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body class="{{ session('id_usuario') ? 'authenticated' : 'guest' }}">
<a class="visually-hidden-focusable skip-link" href="#contenido">Saltar al contenido</a>
@if(session('id_usuario'))
    <aside class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
        <div class="offcanvas-header"><h2 id="sidebarLabel" class="h6">Menú de la clínica</h2><button class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar menú"></button></div>
        <div class="offcanvas-body d-flex flex-column">
            <a class="brand" href="{{ route(\App\Http\Controllers\AuthController::home(session('rol'))) }}"><span class="brand-icon"><i class="bi bi-heart-pulse" aria-hidden="true"></i></span><span>Vida Nuevamente<br><strong>Segura</strong></span></a>
            <div class="sidebar-caption">ESPACIO DE {{ mb_strtoupper(session('rol')) }}</div>
            <nav aria-label="Navegación principal" class="nav flex-column gap-1">
                @php($menus = config('navigation.'.session('rol'),[]))
                @foreach($menus as $item)
                    <a class="nav-link {{ request()->routeIs($item[0].'*') ? 'active' : '' }}" href="{{ route($item[0]) }}"><i class="bi bi-{{ $item[2] }}" aria-hidden="true"></i>{{ $item[1] }}</a>
                @endforeach
            </nav>
            <div class="sidebar-footer"><i class="bi bi-shield-check"></i> Atención con confianza<br><small>Gestión clínica y de citas</small></div>
        </div>
    </aside>
@endif
<div class="main-shell">
    <header class="topbar">
        @if(session('id_usuario'))
        <button class="btn btn-light d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Abrir menú"><i class="bi bi-list"></i></button>
        <span class="text-secondary small d-none d-md-block">Clínica · Vida Nuevamente Segura</span>
        <div class="ms-auto d-flex align-items-center gap-3"><div class="user-info"><strong>{{ session('correo') }}</strong><small>{{ session('rol') }}</small></div><form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary" aria-label="Cerrar sesión"><i class="bi bi-box-arrow-right"></i><span class="d-none d-md-inline ms-2">Salir</span></button></form></div>
        @else
        <a href="{{ route('login') }}" class="guest-brand"><i class="bi bi-heart-pulse me-2"></i>Vida Nuevamente Segura</a><span class="ms-auto small text-secondary d-none d-sm-inline">Su salud, nuestra prioridad</span>
        @endif
    </header>
    <main id="contenido" class="content container-fluid">
        @if(session('success'))<div class="alert alert-success" role="status"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Revise la información</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
    <footer class="page-footer">Vida Nuevamente Segura · {{ now()->year }}</footer>
</div>
<section id="chat-panel" class="chat-panel shadow" hidden aria-label="Asistente Angélica">
    <header class="chat-header"><div><strong>Angélica</strong><small>Asistente de la clínica</small></div><button type="button" id="chat-close" class="btn btn-sm text-white" aria-label="Cerrar chat"><i class="bi bi-x-lg"></i></button></header>
    <div id="chat-messages" class="chat-messages" role="log" aria-live="polite"><p class="chat-bubble">Hola, soy Angélica. ¿En qué puedo ayudarle?</p></div>
    <div class="chat-quick">@foreach(['Especialidades','Horarios y médicos','Mis citas','Requisitos'] as $q)<button type="button" class="btn btn-sm btn-outline-primary" data-chat="{{ $q }}">{{ $q }}</button>@endforeach</div>
    <form id="chat-form" class="chat-form" data-url="{{ route('chatbot') }}"><label class="visually-hidden" for="chat-input">Mensaje</label><input id="chat-input" class="form-control" maxlength="500" placeholder="Escriba su consulta…" required><button class="btn btn-primary" aria-label="Enviar mensaje"><i class="bi bi-send"></i></button></form>
</section>
<button id="chat-toggle" class="chat-toggle" aria-controls="chat-panel" aria-expanded="false" aria-label="Abrir asistente Angélica"><i class="bi bi-chat-heart"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/chatbot.js') }}" defer></script>
@stack('scripts')
</body></html>
