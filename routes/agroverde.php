<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AgroVerde — Rotas Customizadas
|--------------------------------------------------------------------------
|
| Rotas do fork AgroVerde. Carregadas pelo AgroverdeServiceProvider.
| Mantidas separadas de routes/web.php para evitar conflitos no merge
| com o upstream.
|
| Ver AGROVERDE.md — estratégia de fork em 3 camadas.
|
*/

// As rotas dos módulos AgroVerde serão adicionadas aqui conforme
// os módulos forem implementados (Fase A2 do plano 0006).
//
// Exemplo (Fase A2):
//
// Route::middleware(['web', 'auth'])->prefix('agroverde')->group(function () {
//     Route::resource('pet-pathologies', \App\Http\Controllers\Agroverde\PetPathologyController::class);
//     Route::resource('pet-documents', \App\Http\Controllers\Agroverde\PetDocumentController::class);
//     Route::resource('pet-photos', \App\Http\Controllers\Agroverde\PetPhotoController::class);
//     Route::resource('pet-observations', \App\Http\Controllers\Agroverde\PetObservationController::class);
//     Route::resource('pet-videos', \App\Http\Controllers\Agroverde\PetVideoController::class);
//     Route::resource('prescription-templates', \App\Http\Controllers\Agroverde\PrescriptionTemplateController::class);
// });
