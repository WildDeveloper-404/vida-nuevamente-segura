@extends('layouts.app')
@section('title','Inicio')
@section('content')
<div class="welcome"><div class="eyebrow">{{ now()->translatedFormat('l, d \d\e F') }}</div><h1>Bienvenido a su espacio de atención</h1><p class="subtitle mb-0">Organice sus gestiones y encuentre lo que necesita desde un solo lugar.</p></div>
<div class="page-heading"><div><h2>¿Qué desea hacer hoy?</h2><p class="subtitle mb-0">Accesos disponibles para {{ mb_strtolower(session('rol')) }}.</p></div><span class="badge-state state-Confirmada"><i class="bi bi-shield-check me-1"></i>Sesión activa</span></div>
<div class="row g-3">@foreach(config('navigation.'.session('rol'),[]) as $item)@unless(str_ends_with($item[0],'dashboard'))<div class="col-12 col-md-6 col-xl-4"><a class="quick-card" href="{{ route($item[0]) }}"><i class="bi bi-{{ $item[2] }}"></i><h2>{{ $item[1] }}</h2><span class="subtitle">Acceder <span aria-hidden="true">→</span></span></a></div>@endunless @endforeach</div>
@endsection
