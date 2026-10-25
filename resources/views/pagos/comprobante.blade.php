@extends('layouts.app')
@section('title','Comprobante de pago')
@section('content')
<div class="page-heading"><div><div class="eyebrow">Vida Nuevamente Segura</div><h1>Comprobante de pago</h1></div></div>
<div class="card" style="max-width:800px"><div class="card-body"><div class="eyebrow">{{ $pago->comprobante->nro_comprobante }}</div><h2>Pago recibido</h2><dl class="row mt-4"><dt class="col-5">Fecha</dt><dd class="col-7">{{ $pago->fecha_pago }}</dd><dt class="col-5">Titular</dt><dd class="col-7">{{ $pago->comprobante->razon_social }}</dd><dt class="col-5">CI</dt><dd class="col-7">{{ $pago->comprobante->nit_ci }}</dd><dt class="col-5">Concepto</dt><dd class="col-7">{{ $pago->comprobante->concepto }}</dd><dt class="col-5">Método</dt><dd class="col-7">{{ $pago->metodo_pago }}</dd><dt class="col-5">Monto</dt><dd class="col-7">{{ $pago->monto }} {{ strtoupper(config('services.stripe.currency')) }}</dd></dl><a class="btn btn-primary" href="{{ route('pagos.comprobante',[$pago,'pdf'=>1]) }}">Descargar PDF</a><p class="form-text mt-3">Comprobante interno de la clínica. La validez fiscal depende de la configuración y normativa aplicables.</p></div></div>
@endsection
