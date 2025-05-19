<?php

namespace App\Controllers;

use App\Models\Table;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TableController
{
    public function getAll(Request $request, Response $response)
    {
        $tables = Table::all();

        // Agregar información de si la mesa está ocupada
        $tables->map(function ($table) {
            $table->occupied = $table->isOccupied();
            return $table;
        });

        $response->getBody()->write(json_encode($tables));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getOne(Request $request, Response $response, array $args)
    {
        $table = Table::find($args['id']);

        if (!$table) {
            $response->getBody()->write(json_encode(['error' => 'Table not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $table->occupied = $table->isOccupied();
        $currentOrder = $table->getCurrentOrder();

        if ($currentOrder) {
            $table->current_order = $currentOrder->load('items.product');
        }

        $response->getBody()->write(json_encode($table));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response)
    {
        $data = $request->getParsedBody();

        if (!isset($data['number']) || !isset($data['capacity'])) {
            $response->getBody()->write(json_encode(['error' => 'Number and capacity are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Verificar si ya existe una mesa con ese número
        $existingTable = Table::where('number', $data['number'])->first();
        if ($existingTable) {
            $response->getBody()->write(json_encode(['error' => 'A table with this number already exists']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $table = new Table([
            'number' => $data['number'],
            'capacity' => $data['capacity'],
            'status' => 'available'
        ]);

        $table->save();

        $response->getBody()->write(json_encode([
            'success' => true,
            'table' => $table
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args)
    {
        $table = Table::find($args['id']);

        if (!$table) {
            $response->getBody()->write(json_encode(['error' => 'Mesa no encontrada']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // No permitir eliminar mesas ocupadas
        if ($table->isOccupied()) {
            $response->getBody()->write(json_encode(['error' => 'No se puede eliminar una mesa ocupada']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $table->delete();

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
