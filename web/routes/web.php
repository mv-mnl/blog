<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BlogController::class, 'index'])->name('home');
Route::get('/posts/{slug}', [BlogController::class, 'show'])->name('posts.show');
Route::post('/posts/{slug}/comentarios', [CommentController::class, 'store'])->name('comments.store');
Route::get('/etiqueta/{tag:slug}', [BlogController::class, 'tag'])->name('tags.show');
Route::get('/serie/{series:slug}', [BlogController::class, 'series'])->name('series.show');
Route::get('/feed.xml', [BlogController::class, 'feed'])->name('feed');
