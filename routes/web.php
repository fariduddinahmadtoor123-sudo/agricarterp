<?php

use App\Http\Controllers\Catalog\CategoryCatalogController;
use App\Http\Controllers\Catalog\CategoryCatalogImageController;
use Illuminate\Support\Facades\Route;

Route::get('/category-images', CategoryCatalogImageController::class)
    ->name('catalog.category-images');

Route::get('/', [CategoryCatalogController::class, 'index'])
    ->name('catalog.index');

Route::get('/catalog/{categoryId}', [CategoryCatalogController::class, 'show'])
    ->whereNumber('categoryId')
    ->name('catalog.show');
