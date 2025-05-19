<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Acceder a las variables globales
global $authMiddleware, $checkRole;

// Public routes
$app->post('/login', 'App\Controllers\AuthController:login');

// Protected routes
$app->group('/api', function ($app) use ($checkRole) {
    // Tables management - all authenticated users
    $app->get('/tables', 'App\Controllers\TableController:getAll');
    $app->get('/tables/{id}', 'App\Controllers\TableController:getOne');

    // Añadir estas dos rutas para gestión de mesas
    $app->post('/tables', 'App\Controllers\TableController:create')->add($checkRole(['gerente', 'dueño']));
    $app->delete('/tables/{id}', 'App\Controllers\TableController:delete')->add($checkRole(['gerente', 'dueño']));

    // Products management - only manager and owner
    $app->group('/products', function ($app) {
        $app->get('', 'App\Controllers\ProductController:getAll');
        $app->get('/{id}', 'App\Controllers\ProductController:getOne');
        $app->post('', 'App\Controllers\ProductController:create');
        $app->put('/{id}', 'App\Controllers\ProductController:update');
        $app->delete('/{id}', 'App\Controllers\ProductController:delete');
    })->add($checkRole(['gerente', 'dueño']));
})->add($authMiddleware);
