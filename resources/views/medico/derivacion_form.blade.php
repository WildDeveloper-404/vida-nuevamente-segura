@extends('layouts.app')
@section('title','Derivación e interconsulta')
@section('content')
<div class="page-heading"><div><div class="eyebrow">Vida Nuevamente Segura</div><h1>Derivación e interconsulta</h1></div></div>
<div class="card"><form class="card-body" method="post" action="{{ route('medico.derivacion.store',$cita) }}">@csrf<h2>{{ $cita->paciente->persona->nombre_completo }} · {{ $cita->codigo_cita }}</h2><div class="row g-3 mt-2"><x-input name="id_especialidad_destino" label="Especialidad destino" :options="$especialidades->pluck('nombre','id_especialidad')" required/><x-input name="prioridad" label="Prioridad" :options="['Normal'=>'Normal','Preferente'=>'Preferente','Urgente'=>'Urgente']" value="Normal" required/><x-input name="motivo_clinico" label="Motivo clínico" type="textarea" required maxlength="8000"/></div><button class="btn btn-primary mt-4">Registrar derivación</button></form></div>
@endsection
