@extends('layouts.app')
@section('title', 'Emitir receta y exámenes')
@section('content')
<div class="page-heading"><h1>Emitir receta y exámenes</h1></div>
<div class="card"><form class="card-body" method="post" action="{{ route('medico.receta.store',$cita) }}" id="receta-form">
    @csrf
    <h2>{{ $cita->paciente->persona->nombre_completo }} · {{ $cita->codigo_cita }}</h2>
    <p>Alergias confirmadas</p>
    <p class="alert alert-warning" id="alergias-paciente">{{ $cita->paciente->perfilClinico?->alergias ?? 'Sin información' }}</p>
    <div class="row g-3">
        <x-input name="cie10" label="Código CIE-10" maxlength="8" required />
        <x-input name="diagnostico" label="Diagnóstico clínico" type="textarea" maxlength="8000" required />
    </div>
    <h2 class="mt-4">Prescripción y órdenes de laboratorio o imagenología</h2>
    <div id="rx-items"></div>
    <button type="button" class="btn btn-outline-primary my-3" id="add-item">Agregar indicación</button>
    <p class="form-text">Indique el principio activo de cada medicamento. Separe con coma los principios activos de medicamentos combinados.</p>
    <button class="btn btn-primary">Emitir receta</button>
</form></div>
<template id="rx-template">
    <div class="card p-3 mb-3 rx-item">
        <div class="row g-2">
            <label class="col-md-4">Tipo
                <select class="form-select" data-name="tipo_item">
                    <option value="Medicamento">Medicamento</option>
                    <option value="Examen_Laboratorio">Laboratorio o imagenología</option>
                </select>
            </label>
            <label class="col-md-8">Medicamento o examen<input class="form-control" data-name="medicamento_o_examen" maxlength="150" required></label>
            <label class="col-md-6">Principio activo<input class="form-control" data-name="principio_activo" maxlength="150"></label>
            <label class="col-md-6">Dosis<input class="form-control" data-name="dosis" maxlength="80"></label>
            <label class="col-md-6">Frecuencia<input class="form-control" data-name="frecuencia" maxlength="80"></label>
            <label class="col-md-6">Duración<input class="form-control" data-name="duracion" maxlength="60"></label>
        </div>
        <button type="button" class="btn btn-sm btn-link text-danger align-self-end remove-item">Retirar indicación</button>
    </div>
</template>
<dialog id="alerta-alergia" class="border border-danger rounded p-4">
    <h2 class="text-danger">Alerta clínica de alergia</h2>
    <p>El principio activo coincide con una alergia confirmada. Sustituya el medicamento.</p>
    <form method="dialog"><button class="btn btn-danger">Revisar prescripción</button></form>
</dialog>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('receta-form');
    const items = document.getElementById('rx-items');
    const alert = document.getElementById('alerta-alergia');
    const normalize = value => value.toLowerCase().normalize('NFD').replace(/\p{M}/gu, '').trim();
    const allergies = document.getElementById('alergias-paciente').textContent.split(/[,;\n]+/).map(normalize);
    let index = 0;
    function addItem(values = {}) {
        if (items.children.length >= 30) return;
        const fragment = document.getElementById('rx-template').content.cloneNode(true);
        const row = fragment.firstElementChild;
        for (const input of row.querySelectorAll('[data-name]')) {
            input.name = 'items[' + index + '][' + input.dataset.name + ']';
            if (values[input.dataset.name]) input.value = values[input.dataset.name];
        }
        const type = row.querySelector('[data-name="tipo_item"]');
        const active = row.querySelector('[data-name="principio_activo"]');
        const update = () => {
            active.required = type.value === 'Medicamento';
            active.disabled = !active.required;
        };
        update();
        type.addEventListener('change', update);
        row.querySelector('.remove-item').onclick = () => {
            if (items.children.length > 1) row.remove();
        };
        items.append(fragment);
        index++;
    }
    const oldItems = Object.values({{ Illuminate\Support\Js::from(old('items', [])) }});
    for (const item of oldItems.length ? oldItems : [{}]) addItem(item);
    document.getElementById('add-item').onclick = () => addItem();
    form.addEventListener('submit', event => {
        const active = [...items.querySelectorAll('[data-name="principio_activo"]:enabled')]
            .find(input => input.value.split(/[,;+/]+/).map(normalize).some(name => name && allergies.includes(name)));
        if (active) {
            event.preventDefault();
            alert.showModal();
            alert.addEventListener('close', () => active.focus(), {once: true});
        }
    });
    if ({{ str_contains($errors->first('db_error'), 'ALERTA CLÍNICA') ? 'true' : 'false' }}) alert.showModal();
});
</script>
@endpush
@endsection
