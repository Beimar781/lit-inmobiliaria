@props(['name', 'label', 'type' => 'text', 'valor' => null, 'ayuda' => null])

{{-- Campo de formulario con etiqueta y error. Uso: <x-campo name="nombre" label="Nombre" :valor="$modelo?->nombre" /> --}}
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $valor) }}"
           {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    @if ($ayuda)
        <div class="form-text">{{ $ayuda }}</div>
    @endif
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
