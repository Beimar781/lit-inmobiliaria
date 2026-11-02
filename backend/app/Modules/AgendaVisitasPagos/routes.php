<?php

use App\Modules\AgendaVisitasPagos\CU14_AgendarVisita\AgendarVisitaController;
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
