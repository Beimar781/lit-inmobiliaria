{{-- CU12 (vía 1): formulario público "Me interesa". Se incluye en la ficha de la propiedad (CU11). Requiere $propiedad. --}}
<div class="card shadow-sm mb-4" id="me-interesa">
    <div class="card-body">
        <h2 class="h5">Me interesa</h2>
        <p class="text-muted small">Déjanos tus datos y un agente se pondrá en contacto contigo.</p>

        @error('propiedad')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('catalogo.interes', $propiedad) }}" novalidate>
            @csrf

            {{-- Trampa para robots (no se muestra a las personas) --}}
            <div style="position: absolute; left: -9999px" aria-hidden="true">
                <label for="sitio_web">No completes este campo</label>
                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
            </div>

            <x-campo name="nombre" label="Nombre completo" autocomplete="name" />
            <x-campo name="email" label="Correo electrónico" type="email" autocomplete="email" />
            <x-campo name="telefono" label="Teléfono" type="tel" autocomplete="tel"
                     ayuda="Indica tu correo o tu teléfono (al menos uno)." />

            <div class="mb-3">
                <label for="mensaje" class="form-label">Mensaje <span class="text-muted">(opcional)</span></label>
                <textarea id="mensaje" name="mensaje" rows="3"
                          class="form-control @error('mensaje') is-invalid @enderror">{{ old('mensaje') }}</textarea>
                @error('mensaje')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary w-100">Me interesa</button>
        </form>
    </div>
</div>
