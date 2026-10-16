@php($esMismo = $usuario && auth()->id() === $usuario->idusuario)

<x-campo name="nombre" label="Nombre completo" :valor="$usuario?->nombre" />
<x-campo name="email" label="Correo electrónico" type="email" :valor="$usuario?->email" />
<x-campo name="telefono" label="Teléfono" :valor="$usuario?->telefono" />

<div class="row">
    <div class="col-md-6">
        @if ($esMismo)
            {{-- No puede cambiarse a sí mismo el rol ni el estado --}}
            <input type="hidden" name="idrol" value="{{ $usuario->idrol }}">
            <input type="hidden" name="estado" value="{{ $usuario->estado }}">
            <x-seleccion name="idrol_vista" label="Rol" :opciones="$roles" :valor="$usuario->idrol" disabled />
        @else
            <x-seleccion name="idrol" label="Rol" :opciones="$roles" :valor="$usuario?->idrol" vacio="Selecciona un rol" />
        @endif
    </div>
    <div class="col-md-6">
        @if ($esMismo)
            <x-seleccion name="estado_vista" label="Estado" :opciones="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :valor="$usuario->estado" disabled />
        @else
            <x-seleccion name="estado" label="Estado" :opciones="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :valor="$usuario?->estado ?? 'ACTIVO'" />
        @endif
    </div>
</div>

<div class="mb-3">
    <label for="password" class="form-label">Contraseña</label>
    <x-password-input name="password" />
    <div class="form-text">
        @if ($usuario) Déjala vacía para conservar la actual. @endif
        Mínimo 8 caracteres, con letras y números.
    </div>
</div>

<div class="mb-3">
    <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
    <x-password-input name="password_confirmation" />
</div>
