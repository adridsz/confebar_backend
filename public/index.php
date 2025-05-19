<?php

use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Create DI container
$container = new Container();
require __DIR__ . '/../src/config/container.php';

// Initialize database connection immediately
$container->get('db');

// Create app
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add middleware and load auth middleware
require __DIR__ . '/../src/middleware/middleware.php';

// Asegurarse de que las variables estén disponibles
global $authMiddleware, $checkRole;

// Register routes
require __DIR__ . '/../src/routes/api.php';

// Run app
$app->run();
