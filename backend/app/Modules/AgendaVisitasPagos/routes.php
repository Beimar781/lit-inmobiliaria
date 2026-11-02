<?php

use App\Modules\AgendaVisitasPagos\CU14_AgendarVisita\AgendarVisitaController;
use App\Http\Middleware\RolMiddleware;
use App\Models\Rol;
use App\Modules\AgendaVisitasPagos\CU15_ReagendarVisita\ReagendarVisitaController;
use App\Modules\AgendaVisitasPagos\CU16_CancelarVisita\CancelarVisitaController;
use App\Modules\AgendaVisitasPagos\CU17_VerCalendarioVisitas\CalendarioVisitasController;
use Illuminate\Support\Facades\Route;

// Paquete P5 - Agenda, Visitas y Pagos

// CU14: Agendar visita. El cliente público agenda sin iniciar sesión desde la ficha (CU11); el Agente y el Administrador,
// con sesión iniciada, agendan en nombre de un cliente (el formulario muestra además la lista de prospectos).
Route::get('/catalogo/{propiedad}/agendar-visita', [AgendarVisitaController::class, 'crear'])
    ->whereNumber('propiedad')
    ->name('visitas.create');
Route::post('/catalogo/{propiedad}/agendar-visita', [AgendarVisitaController::class, 'guardar'])
    ->whereNumber('propiedad')
    ->middleware('throttle:6,1')   // máximo 6 envíos por minuto por conexión, como el "Me interesa"
    ->name('visitas.store');
Route::get('/visitas/{visita}/confirmacion', [AgendarVisitaController::class, 'confirmacion'])
    ->whereNumber('visita')
    ->middleware('signed')         // el enlace de la confirmación va firmado
    ->name('visitas.confirmacion');

// Agente y Administrador (el Agente solo ve lo suyo: se comprueba en el controlador)
Route::middleware(['auth', RolMiddleware::class . ':' . Rol::ADMINISTRADOR . ',' . Rol::AGENTE])->group(function () {

    // CU17: Ver calendario de visitas (mes o semana) y detalle de la visita
    Route::get('/agenda/visitas', [CalendarioVisitasController::class, 'index'])->name('visitas.calendario');
    Route::get('/agenda/visitas/{visita}', [CalendarioVisitasController::class, 'show'])->whereNumber('visita')->name('visitas.show');
    // CU15: Reagendar visita (se entra desde el detalle de la visita; el Agente solo con las suyas: se comprueba en el controlador)
    Route::get('/agenda/visitas/{visita}/reagendar', [ReagendarVisitaController::class, 'editar'])->whereNumber('visita')->name('visitas.reagendar');
    Route::put('/agenda/visitas/{visita}/reagendar', [ReagendarVisitaController::class, 'actualizar'])->whereNumber('visita')->name('visitas.reagendar.guardar');

    // CU16: Cancelar visita (pide el motivo y una confirmación)
    Route::get('/agenda/visitas/{visita}/cancelar', [CancelarVisitaController::class, 'confirmar'])->whereNumber('visita')->name('visitas.cancelar');
    Route::put('/agenda/visitas/{visita}/cancelar', [CancelarVisitaController::class, 'cancelar'])->whereNumber('visita')->name('visitas.cancelar.confirmar');

    // Acción del detalle (decisión del equipo): marcar la visita como realizada
    Route::patch('/agenda/visitas/{visita}/realizada', [CalendarioVisitasController::class, 'marcarRealizada'])->whereNumber('visita')->name('visitas.realizada');
});
