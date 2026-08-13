<?php

use App\Http\Controllers\Portal\Auth\LoginController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\PetController;
use App\Http\Controllers\Portal\AppointmentController;
use App\Http\Controllers\Portal\InvoiceController;
use App\Http\Controllers\Portal\MedicalRecordController;
use App\Http\Controllers\Portal\ExamController;
use App\Http\Controllers\Portal\PrescriptionController;
use App\Http\Controllers\Portal\DocController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'login')->name('portal.home');

// Login único: o acesso de todos os perfis (inclusive Tutor) é feito por /login.
// As rotas de autenticação do portal foram desativadas para manter uma única
// tela de login. Mantém-se o redirect (caminho relativo ao prefixo 'portal') para
// não quebrar links/bookmarks antigos.
Route::redirect('login', '/login')->name('portal.login.redirect');
Route::redirect('register', '/login')->name('portal.register.redirect');
Route::redirect('forgot-password', '/login')->name('portal.password.request.redirect');
Route::redirect('reset-password', '/login')->name('portal.password.reset.redirect');

// Documentation diagrams — slug sem extensão para nginx não interceptar
Route::get('docs/imagem/{slug}', function (string $slug) {
    $slug = basename($slug);
    $path = storage_path("docs/diagrams/{$slug}.svg");
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, ['Content-Type' => 'image/svg+xml']);
})->name('portal.docs.diagrams');

Route::middleware('auth:tutor')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('portal.logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('portal.dashboard');

    Route::get('pets', [PetController::class, 'index'])->name('portal.pets.index');
    Route::get('pets/{id}', [PetController::class, 'show'])->name('portal.pets.show');

    Route::get('appointments', [AppointmentController::class, 'index'])->name('portal.appointments.index');
    Route::get('appointments/create', [AppointmentController::class, 'create'])->name('portal.appointments.create');
    Route::get('appointments/{id}', [AppointmentController::class, 'show'])->name('portal.appointments.show');
    Route::post('appointments', [AppointmentController::class, 'store'])->name('portal.appointments.store');

    Route::get('vet-availability/available-vets', [\App\Http\Controllers\Portal\VetAvailabilityController::class, 'availableVets'])->name('portal.vet-availability.available-vets');
    Route::get('vet-availability/vet-slots', [\App\Http\Controllers\Portal\VetAvailabilityController::class, 'vetSlots'])->name('portal.vet-availability.vet-slots');
    Route::get('vet-availability/vet-dates', [\App\Http\Controllers\Portal\VetAvailabilityController::class, 'vetDates'])->name('portal.vet-availability.vet-dates');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('portal.invoices.index');
    Route::get('invoices/{id}', [InvoiceController::class, 'show'])->name('portal.invoices.show');
    Route::get('invoices/{id}/download', [InvoiceController::class, 'download'])->name('portal.invoices.download');
    Route::post('invoices/{id}/checkout', [InvoiceController::class, 'checkout'])->name('portal.invoices.checkout');

    Route::get('medical-records', [MedicalRecordController::class, 'index'])->name('portal.medical-records.index');
    Route::get('medical-records/{id}', [MedicalRecordController::class, 'show'])->name('portal.medical-records.show');

    Route::get('exams', [ExamController::class, 'index'])->name('portal.exams.index');

    Route::get('prescriptions', [PrescriptionController::class, 'index'])->name('portal.prescriptions.index');

    Route::get('vaccinations', [\App\Http\Controllers\Portal\VaccinationController::class, 'index'])->name('portal.vaccinations.index');

    Route::get('docs', [DocController::class, 'index'])->name('portal.docs.index');
    Route::get('docs/{page}', [DocController::class, 'show'])->name('portal.docs.show');
});
