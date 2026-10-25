@extends('layouts.app')
@section('title','Recuperar acceso')
@section('content')
<div class="card mx-auto" style="max-width:520px"><div class="card-body"><div class="eyebrow">Acceso a su cuenta</div><h1>Recuperar contraseña</h1><p class="subtitle">Enviaremos un enlace válido por 30 minutos.</p><form method="post" action="{{ route('password.email') }}">@csrf<div class="row g-3"><x-input name="correo" label="Correo electrónico" type="email" required/></div><button class="btn btn-primary w-100 mt-4">Enviar enlace</button></form><a class="d-block mt-3" href="{{ route('login') }}">Volver al ingreso</a></div></div>
@endsection
