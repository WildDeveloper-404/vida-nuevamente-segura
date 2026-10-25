@extends('layouts.app')
@section('title','Servicio no disponible')
@section('content')<div class="card"><div class="card-body"><h1>No pudimos completar la consulta</h1><p>{{ $message }}</p><a class="btn btn-primary" href="{{ url()->current() }}">Reintentar</a></div></div>@endsection
