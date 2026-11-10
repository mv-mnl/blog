<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/posts');
Route::post('/upload', [PostController::class, 'upload'])->name('upload');
Route::resource('posts', PostController::class)->except('show');
Route::get('/posts/{post}/historial/{revision}', [PostController::class, 'revision'])->name('posts.revision');
Route::post('/posts/{post}/historial/{revision}/restaurar', [PostController::class, 'restore'])->name('posts.restore');

Route::get('/comentarios', [CommentController::class, 'index'])->name('comments.index');
Route::patch('/comentarios/{comment}', [CommentController::class, 'update'])->name('comments.update');
Route::delete('/comentarios/spam', [CommentController::class, 'destroySpam'])->name('comments.destroy-spam');
Route::delete('/comentarios/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

Route::get('/estadisticas', [StatsController::class, 'index'])->name('stats.index');
