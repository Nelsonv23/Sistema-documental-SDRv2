<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\TrashController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/', [FolderController::class, 'index'])->name('home');
    Route::redirect('/home', '/');

    // Explorador y gestión de carpetas
    Route::get('/folders', [FolderController::class, 'index'])->name('folders.index');
    Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::get('/folders/{folder}', [FolderController::class, 'show'])->name('folders.show');
    Route::patch('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::get('/folders/{folder}/permissions', [FolderController::class, 'permissions'])->name('folders.permissions');
    Route::post('/folders/{folder}/permissions', [FolderController::class, 'storePermission'])->name('folders.permissions.store');
    Route::delete('/folders/{folder}/permissions/{permission}', [FolderController::class, 'destroyPermission'])->name('folders.permissions.destroy');

    // Documentos: detalle, duplicados, subida unitaria, vista previa, descarga, papelera y comentarios
    Route::post('/folders/{folder}/documents/check-duplicate', [DocumentController::class, 'checkDuplicate'])
        ->name('documents.check-duplicate');
    Route::post('/folders/{folder}/documents', [DocumentController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::post('/documents/{document}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // Búsqueda híbrida
    Route::get('/search', [SearchController::class, 'index'])->name('documents.search');

    // Notificaciones
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');

    // Mi Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('/users/{user}/avatar', [ProfileController::class, 'showAvatar'])->name('users.avatar');

    // Administración (solo Administrador)
    Route::middleware('role:Administrador')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::prefix('users')->name('users.')->group(function () {
                Route::get('/', [AdminUserController::class, 'index'])->name('index');
                Route::get('/create', [AdminUserController::class, 'create'])->name('create');
                Route::post('/', [AdminUserController::class, 'store'])->name('store');
                Route::get('/{user}/edit', [AdminUserController::class, 'edit'])->name('edit');
                Route::patch('/{user}', [AdminUserController::class, 'update'])->name('update');
                Route::patch('/{user}/role', [AdminUserController::class, 'updateRole'])->name('update-role');
                Route::patch('/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('toggle-status');
            });

            Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
            Route::post('/trash/{document}/restore', [TrashController::class, 'restore'])->name('trash.restore');

            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        });
});
