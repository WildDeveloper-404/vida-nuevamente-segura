@extends('layouts.app')
@section('title', 'Pago de cita')
@section('content')
<div class="page-heading"><div><div class="eyebrow">Reserva</div><h1>Pago de cita</h1></div></div>
<div class="card" style="max-width:800px"><div class="card-body">
    <div class="eyebrow">Reserva {{ $cita->codigo_cita }}</div>
    <h2>{{ $cita->especialidad->nombre }}</h2>
    <p>{{ $cita->paciente->persona->nombre_completo }}<br>{{ $cita->medico->persona->nombre_completo }}<br>{{ $cita->fecha_cita }} · {{ $cita->hora_inicio }}</p>
    <p class="display-6">{{ $cita->arancel_reservado }} <small class="fs-6">{{ strtoupper($cita->moneda_reservada) }}</small></p>
    @if($cita->pago)
        <div class="alert alert-success">Pago registrado.</div>
        <a class="btn btn-primary" href="{{ route('pagos.comprobante',$cita->pago) }}">Ver comprobante</a>
    @elseif(in_array($cita->estado,['Programada','Confirmada'],true) && $cita->arancel_reservado > 0)
        @if(request('resultado') === 'pendiente')
            <div class="alert alert-info">Esperando la confirmación de la pasarela. Actualice esta página en unos instantes.</div>
        @elseif(request('resultado') === 'cancelado')
            <div class="alert alert-warning">El pago no se completó. Puede intentarlo nuevamente.</div>
        @endif
        @if($cita->estado === 'Programada' && $cita->moneda_reservada === strtolower(config('services.stripe.currency')) && preg_match('/^(sk|rk)_test_/',(string) config('services.stripe.secret')))
            <form method="post" action="{{ route('pagos.stripe',$cita) }}">
                @csrf
                <button class="btn btn-primary">Pagar con tarjeta en Stripe (prueba) <i class="bi bi-arrow-up-right"></i></button>
            </form>
            <p class="form-text">El cobro se confirma al recibir el evento firmado de la pasarela.</p>
        @else
            <p class="form-text">El pago con tarjeta no está habilitado para esta reserva. Puede pagar en efectivo en caja.</p>
        @endif
        @if(session('rol') === 'Recepcionista')
            <hr>
            <h2>Registrar efectivo en caja</h2>
            <form method="post" action="{{ route('pagos.presencial',$cita) }}">
                @csrf
                <input type="hidden" name="referencia" value="{{ old('referencia',(string) \Illuminate\Support\Str::uuid()) }}">
                <div class="row g-3">
                    <x-input name="monto" label="Monto recibido" type="number" :value="$cita->arancel_reservado" step="0.01" min="0.01" required />
                </div>
                <button class="btn btn-outline-primary mt-3">Registrar cobro recibido</button>
            </form>
        @endif
    @else
        <p class="form-text">Esta cita no tiene un cobro pendiente.</p>
    @endif
</div></div>
@endsection
