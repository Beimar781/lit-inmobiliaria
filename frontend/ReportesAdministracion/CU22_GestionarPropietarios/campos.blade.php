<x-campo name="nombre" label="Nombre" :valor="$propietario?->nombre" maxlength="255" />
<x-campo name="telefono" label="Teléfono (opcional)" :valor="$propietario?->telefono" />
<x-campo name="email" label="Correo (opcional)" type="email" :valor="$propietario?->email" />
<div class="mb-3">
    <label for="direccion" class="form-label">Dirección (opcional)</label>
    <textarea id="direccion" name="direccion" rows="2" maxlength="500"
              class="form-control @error('direccion') is-invalid @enderror">{{ old('direccion', $propietario?->direccion) }}</textarea>
    @error('direccion')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>