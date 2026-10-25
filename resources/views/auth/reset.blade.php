@extends('layouts.app')
@section('title','Nueva contraseña')
@section('content')
<div class="card mx-auto" style="max-width:600px"><div class="card-body"><h1>Nueva contraseña</h1><form method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><div class="row g-3"><x-input name="correo" label="Correo electrónico" type="email" :value="request('correo')" required/><x-input name="password" label="Nueva contraseña" type="password" minlength="10" required autocomplete="new-password"/><x-input name="password_confirmation" label="Repetir contraseña" type="password" minlength="10" required autocomplete="new-password"/></div><button class="btn btn-primary mt-4">Guardar contraseña</button></form></div></div>
@endsection
