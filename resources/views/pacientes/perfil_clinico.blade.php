@extends('layouts.app')
@section('title', 'Perfil clínico')
@section('content')
@php
    $perfil = $paciente->perfilClinico;
    $esPaciente = session('rol') === 'Paciente';
@endphp
<div class="page-heading"><div><div class="eyebrow">Expediente</div><h1>Perfil clínico</h1></div></div>
<div class="card"><div class="card-body">
    <h2>{{ $paciente->persona->nombre_completo }}</h2>
    <p class="subtitle">{{ $paciente->codigo_paciente }}</p>
    <form method="post" action="{{ route('perfil.update', $paciente->id_paciente) }}">
        @csrf @method('PUT')
        <div class="row g-3">
            @if($esPaciente)
                <x-input name="telefono" label="Teléfono" :value="$paciente->persona->telefono" maxlength="20" required />
                <x-input name="direccion" label="Dirección" :value="$paciente->persona->direccion" maxlength="200" />
            @else
                <x-input name="tipo_sangre" label="Tipo de sangre" :value="$perfil?->tipo_sangre" :options="array_combine(['A+','A-','B+','B-','AB+','AB-','O+','O-'], ['A+','A-','B+','B-','AB+','AB-','O+','O-'])" />
                <x-input name="alergias" label="Alergias confirmadas (separe con coma)" type="textarea" :value="$perfil?->alergias" maxlength="4000" required />
                <x-input name="enfermedades_base" label="Enfermedades de base" type="textarea" :value="$perfil?->enfermedades_base" maxlength="4000" required />
                <x-input name="medicamentos_habituales" label="Medicación habitual" type="textarea" :value="$perfil?->medicamentos_habituales" maxlength="4000" required />
                <x-input name="antecedentes_relevantes" label="Otros antecedentes" type="textarea" :value="$perfil?->antecedentes_relevantes" maxlength="8000" />
            @endif
            <x-input name="contacto_emergencia_nombre" label="Contacto de emergencia" :value="$perfil?->contacto_emergencia_nombre" maxlength="150" />
            <x-input name="contacto_emergencia_parentesco" label="Parentesco" :value="$perfil?->contacto_emergencia_parentesco" maxlength="60" />
            <x-input name="contacto_emergencia_telefono" label="Teléfono de emergencia" :value="$perfil?->contacto_emergencia_telefono" maxlength="20" />
        </div>
        <button class="btn btn-primary mt-4">{{ $esPaciente ? 'Guardar contacto' : 'Guardar antecedentes' }}</button>
    </form>
    @if($esPaciente)
        <h2 class="mt-4">Antecedentes registrados por su médico</h2>
        <dl class="row">
            @foreach(['Tipo de sangre'=>'tipo_sangre','Alergias'=>'alergias','Enfermedades de base'=>'enfermedades_base','Medicación habitual'=>'medicamentos_habituales','Otros antecedentes'=>'antecedentes_relevantes'] as $label=>$key)
                <dt class="col-sm-4">{{ $label }}</dt>
                <dd class="col-sm-8">{{ $perfil?->$key ?? 'Sin registrar' }}</dd>
            @endforeach
        </dl>
    @endif
</div></div>
<section class="card mt-4" aria-labelledby="recetas-titulo">
    <div class="card-body">
        <h2 id="recetas-titulo">Recetas médicas</h2>
        @forelse($recetas as $receta)
            <article class="border rounded p-3 mb-3">
                <h3 class="h5">Receta #{{ $receta->id_receta }}</h3>
                <p>{{ $receta->fecha_emision }} · {{ $receta->cita->medico->persona->nombre_completo }} · {{ $receta->cita->especialidad->nombre }}</p>
                <p><strong>Diagnóstico ({{ $receta->cie10 }}):</strong> {{ $receta->diagnostico }}</p>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Medicamento / examen</th><th>Dosis</th><th>Frecuencia</th><th>Duración</th></tr></thead>
                        <tbody>
                        @foreach($receta->detalles as $detalle)
                            <tr>
                                <td>{{ $detalle->medicamento_o_examen }} @if($detalle->principio_activo)<br><small>{{ $detalle->principio_activo }}</small>@endif</td>
                                <td>{{ $detalle->dosis ?: '—' }}</td><td>{{ $detalle->frecuencia ?: '—' }}</td><td>{{ $detalle->duracion ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('recetas.pdf',$receta->id_receta) }}">Descargar receta PDF</a>
            </article>
        @empty
            <p>Todavía no hay recetas emitidas para mostrar.</p>
        @endforelse
        {{ $recetas->links() }}
    </div>
</section>
@endsection
