@extends('layouts.app')

@section('titulo', 'Nueva reserva - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('reservas.index') }}" class="text-decoration-none">&larr; Volver a reservas</a>
<h1 class="h3 my-3">Nueva reserva</h1>

<div class="row">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('reservas.store') }}" novalidate>
                    @csrf

                    <x-seleccion name="idpropiedad" label="Propiedad disponible" :opciones="$propiedades" :valor="$propiedadElegida"
                                 vacio="— Elige la propiedad —" />

                    <x-seleccion name="idcliente" label="Cliente del CRM" :opciones="$clientes" :valor="$clienteElegido"
                                 vacio="— Elige el cliente —" />
                    <div class="form-text mb-3 mt-n2">
                        ¿El cliente no está en la lista? Regístralo primero en <a href="{{ route('prospectos.index') }}">Prospectos CRM</a>.
                    </div>

                    <x-campo name="fechaplazo" label="Plazo de la reserva (hasta qué día)" type="date" :min="$minimo"
                             ayuda="La propiedad queda reservada hasta el final de ese día. Después vuelve a estar disponible." />

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Guardar reserva</button>
                        <a href="{{ route('reservas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
