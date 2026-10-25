@extends('layouts.app')
@section('title','Verificación de receta')
@section('content')
<div class="page-heading"><div><div class="eyebrow">Vida Nuevamente Segura</div><h1>Verificación de receta</h1></div></div>
<div class="card"><div class="card-body"><span class="badge-state state-Confirmada">Documento registrado</span><h2 class="mt-3">Receta #{{ $numero }}</h2><p>Emitida el {{ $fecha }} por Vida Nuevamente Segura.</p><p class="subtitle">El contenido clínico está disponible únicamente para el paciente titular y el médico tratante dentro de su sesión.</p></div></div>
@endsection
