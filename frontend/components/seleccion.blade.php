@props(['name', 'label', 'opciones' => [], 'valor' => null, 'vacio' => null])

{{-- Lista desplegable con etiqueta y error. $opciones = [valor => texto] --}}
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}"
            {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @if ($vacio !== null)
            <option value="">{{ $vacio }}</option>
        @endif
        @foreach ($opciones as $clave => $texto)
            <option value="{{ $clave }}" @selected((string) old($name, $valor) === (string) $clave)>{{ $texto }}</option>
        @endforeach
    </select>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
