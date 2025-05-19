<?php

namespace App\Controllers;

use App\Models\Product;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductController
{
    public function getAll(Request $request, Response $response)
    {
        $products = Product::all();

        $response->getBody()->write(json_encode($products));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getOne(Request $request, Response $response, array $args)
    {
        $product = Product::find($args['id']);

        if (!$product) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($product));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response)
    {
        $data = $request->getParsedBody();

        if (!isset($data['name']) || !isset($data['price']) || !isset($data['category'])) {
            $response->getBody()->write(json_encode(['error' => 'Name, price and category are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $product = new Product([
            'name' => $data['name'],
            'price' => $data['price'],
            'stock' => $data['stock'] ?? 0,
            'category' => $data['category']
        ]);

        $product->save();

        $response->getBody()->write(json_encode([
            'success' => true,
            'product' => $product
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args)
    {
        $product = Product::find($args['id']);

        if (!$product) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $data = $request->getParsedBody();

        // Update only provided fields
        if (isset($data['name'])) {
            $product->name = $data['name'];
        }

        if (isset($data['price'])) {
            $product->price = $data['price'];
        }

        if (isset($data['stock'])) {
            $product->stock = $data['stock'];
        }

        if (isset($data['category'])) {
            $product->category = $data['category'];
        }

        $product->save();

        $response->getBody()->write(json_encode([
            'success' => true,
            'product' => $product
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args)
    {
        $product = Product::find($args['id']);

        if (!$product) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Check if product is in any active order
        $inUse = $product->orderItems()->whereHas('order', function ($query) {
            $query->where('status', 'active');
        })->exists();

        if ($inUse) {
            $response->getBody()->write(json_encode(['error' => 'Cannot delete product that is in an active order']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $product->delete();

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
