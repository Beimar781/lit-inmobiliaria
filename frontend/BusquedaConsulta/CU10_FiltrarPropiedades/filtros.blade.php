{{-- CU10: panel de filtros del catálogo. Se incluye dentro del formulario de búsqueda del CU9. --}}
<details class="mb-3" @if ($filtros->hay() || $errors->any()) open @endif>
    <summary class="fw-semibold" style="cursor: pointer">Filtros de búsqueda</summary>

    <div class="row g-3 mt-1">
        <div class="col-md-3">
            <label for="tipo" class="form-label small mb-1">Tipo de operación</label>
            <select id="tipo" name="tipo" class="form-select">
                <option value="">Todas</option>
                @foreach (\App\Models\Propiedad::TIPOS as $tipo)
                    <option value="{{ $tipo }}" @selected(request('tipo') === $tipo)>{{ $tipo }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="categoria" class="form-label small mb-1">Categoría</label>
            <select id="categoria" name="categoria" class="form-select">
                <option value="">Todas</option>
                @foreach ($categorias as $id => $nombre)
                    <option value="{{ $id }}" @selected((string) request('categoria') === (string) $id)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="precio_min" class="form-label small mb-1">Precio mínimo (USD)</label>
            <input type="number" id="precio_min" name="precio_min" min="0" step="any" value="{{ request('precio_min') }}"
                   class="form-control @error('precio_min') is-invalid @enderror">
            @error('precio_min') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label for="precio_max" class="form-label small mb-1">Precio máximo (USD)</label>
            <input type="number" id="precio_max" name="precio_max" min="0" step="any" value="{{ request('precio_max') }}"
                   class="form-control @error('precio_max') is-invalid @enderror">
            @error('precio_max') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3">
            <label for="zona" class="form-label small mb-1">Zona</label>
            <select id="zona" name="zona" class="form-select">
                <option value="">Todas</option>
                @foreach ($zonas as $zona)
                    <option value="{{ $zona }}" @selected(request('zona') === $zona)>{{ $zona }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="superficie_min" class="form-label small mb-1">Superficie mínima (m²)</label>
            <input type="number" id="superficie_min" name="superficie_min" min="0" step="any" value="{{ request('superficie_min') }}"
                   class="form-control @error('superficie_min') is-invalid @enderror">
            @error('superficie_min') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label for="superficie_max" class="form-label small mb-1">Superficie máxima (m²)</label>
            <input type="number" id="superficie_max" name="superficie_max" min="0" step="any" value="{{ request('superficie_max') }}"
                   class="form-control @error('superficie_max') is-invalid @enderror">
            @error('superficie_max') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <div class="row g-2">
                <div class="col-6">
                    <label for="habitaciones" class="form-label small mb-1">Habitaciones (mín.)</label>
                    <input type="number" id="habitaciones" name="habitaciones" min="0" value="{{ request('habitaciones') }}"
                           class="form-control @error('habitaciones') is-invalid @enderror">
                </div>
                <div class="col-6">
                    <label for="banos" class="form-label small mb-1">Baños (mín.)</label>
                    <input type="number" id="banos" name="banos" min="0" value="{{ request('banos') }}"
                           class="form-control @error('banos') is-invalid @enderror">
                </div>
            </div>
        </div>
    </div>
</details>
