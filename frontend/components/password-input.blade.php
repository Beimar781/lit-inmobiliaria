@props(['name', 'id' => null, 'autofocus' => false])

@php($id = $id ?? $name)

{{-- Campo de contraseña con botón para mostrar/ocultar. Uso: <x-password-input name="password" /> --}}
<div class="input-group has-validation">
    <input type="password" id="{{ $id }}" name="{{ $name }}"
           {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
           @if ($autofocus) autofocus @endif>
    <button type="button" class="btn btn-outline-secondary" style="min-width: 5.5rem"
            aria-label="Mostrar u ocultar contraseña"
            onclick="var i = document.getElementById('{{ $id }}'); var oculto = i.type === 'password'; i.type = oculto ? 'text' : 'password'; this.textContent = oculto ? 'Ocultar' : 'Mostrar';">Mostrar</button>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
