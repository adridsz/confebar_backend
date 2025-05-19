<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Acceder a las variables globales
global $authMiddleware, $checkRole;

// Public routes
$app->post('/login', 'App\Controllers\AuthController:login');

// Protected routes
$app->group('/api', function ($app) use ($checkRole) {
})->add($authMiddleware);
