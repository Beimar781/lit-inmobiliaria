{{-- CU14: botón "Agendar visita". Se incluye en la ficha de la propiedad (CU11), solo si está disponible. Requiere $propiedad. --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Agendar visita</h2>
        <p class="text-muted small">Elige el día y la hora para conocer la propiedad en persona.</p>
        <a href="{{ route('visitas.create', $propiedad) }}" class="btn btn-success w-100">Agendar visita</a>
    </div>
</div>
