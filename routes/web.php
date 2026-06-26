<?php

use App\Http\Controllers\Catalog\CategoryCatalogController;
use App\Http\Controllers\Catalog\CategoryCatalogImageController;
use App\Http\Controllers\Catalog\ProductCatalogImageController;
use App\Http\Controllers\OnlineStore\StorePageController;
use Illuminate\Support\Facades\Route;

Route::get('/category-images', CategoryCatalogImageController::class)
    ->name('catalog.category-images');

Route::get('/product-images', ProductCatalogImageController::class)
    ->name('catalog.product-images');

Route::get('/store/footer-logo', [StorePageController::class, 'footerLogo'])
    ->name('store.footer-logo');

Route::post('/contact', [StorePageController::class, 'contact'])
    ->name('store.contact');

Route::get('/page/{slug}', [StorePageController::class, 'show'])
    ->name('store.page');

Route::get('/', [CategoryCatalogController::class, 'index'])
    ->name('catalog.index');

Route::get('/catalog/{categoryId}', [CategoryCatalogController::class, 'show'])
    ->whereNumber('categoryId')
    ->name('catalog.show');

Route::view('/register', 'register')
    ->name('staff.register');
