<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HostingController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [HostingController::class, 'index'])->name('dashboard');
    Route::post('/accounts', [HostingController::class, 'account'])->name('accounts.create');
    Route::post('/accounts/{account}/domains', [HostingController::class, 'domain'])->name('domains.create');
    Route::post('/domains/{domain}/certificate', [HostingController::class, 'certificate'])->name('certificates.create');
    Route::post('/accounts/{account}/databases', [HostingController::class, 'database'])->name('databases.create');
    Route::get('/accounts/{account}/files', [FileController::class, 'index'])->name('files.index');
    Route::get('/accounts/{account}/files/read', [FileController::class, 'read'])->name('files.read');
    Route::post('/accounts/{account}/files/write', [FileController::class, 'write'])->name('files.write');
    Route::post('/accounts/{account}/files/delete', [FileController::class, 'delete'])->name('files.delete');
});
