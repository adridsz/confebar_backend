<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Hacer global el middleware para que esté disponible en otros archivos
global $authMiddleware, $checkRole;

$authMiddleware = function (Request $request, RequestHandler $handler) {
    $response = new Response();

    // Verificar si la solicitud es OPTIONS (preflight) y permitir pasar
    if ($request->getMethod() === 'OPTIONS') {
        return $handler->handle($request);
    }

    $header = $request->getHeaderLine('Authorization');

    if (!$header) {
        $response->getBody()->write(json_encode([
            'error' => 'Token required'
        ]));
        return $response->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }

    $token = trim(str_replace('Bearer', '', $header));

    try {
        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
        $request = $request->withAttribute('user', $decoded);
        return $handler->handle($request);
    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => 'Invalid token'
        ]));
        return $response->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
};

// Role-based middleware factory
$checkRole = function ($allowedRoles) {
    return function (Request $request, RequestHandler $handler) use ($allowedRoles) {
        $user = $request->getAttribute('user');
        if (!$user || !in_array($user->role, $allowedRoles)) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Unauthorized role']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
        return $handler->handle($request);
    };
};
