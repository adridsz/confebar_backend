<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\Product;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class OrderController
{
    public function create(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $tableId = $args['id'];

        // Verificar si la mesa existe
        $table = Table::find($tableId);
        if (!$table) {
            $response->getBody()->write(json_encode(['error' => 'Table not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Verificar si la mesa ya tiene un pedido activo
        if ($table->isOccupied()) {
            $response->getBody()->write(json_encode(['error' => 'Table already has an active order']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Crear orden
        $order = new Order([
            'table_id' => $tableId,
            'user_id' => $user->id,
            'status' => 'active',
            'total' => 0
        ]);
        $order->save();

        // Procesar items si los hay
        if (isset($data['items']) && is_array($data['items'])) {
            $total = 0;

            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);
                if ($product && $product->stock >= $item['quantity']) {
                    $subtotal = $product->price * $item['quantity'];
                    $total += $subtotal;

                    $orderItem = new OrderItem([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal
                    ]);
                    $orderItem->save();

                    // Actualizar stock
                    $product->stock -= $item['quantity'];
                    $product->save();
                }
            }

            $order->total = $total;
            $order->save();
        }

        // Cambiar estado de la mesa
        $table->status = 'occupied';
        $table->save();

        $response->getBody()->write(json_encode(['success' => true, 'order' => $order]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();
        $orderId = $args['orderId'];
        $tableId = $args['id'];

        $order = Order::where('id', $orderId)
            ->where('table_id', $tableId)
            ->where('status', 'active')
            ->first();

        if (!$order) {
            $response->getBody()->write(json_encode(['error' => 'Active order not found for this table']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Procesar nuevos items
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);
                if ($product && $product->stock >= $item['quantity']) {
                    // Verificar si ya existe el item
                    $existingItem = OrderItem::where('order_id', $order->id)
                        ->where('product_id', $product->id)
                        ->first();

                    if ($existingItem) {
                        // Actualizar cantidad y subtotal
                        $additionalQuantity = $item['quantity'];
                        $existingItem->quantity += $additionalQuantity;
                        $existingItem->subtotal = $existingItem->quantity * $product->price;
                        $existingItem->save();
                    } else {
                        // Crear nuevo item
                        $subtotal = $product->price * $item['quantity'];
                        $orderItem = new OrderItem([
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'quantity' => $item['quantity'],
                            'subtotal' => $subtotal
                        ]);
                        $orderItem->save();
                    }

                    // Actualizar stock
                    $product->stock -= $item['quantity'];
                    $product->save();
                }
            }

            // Recalcular total
            $order->total = $order->calculateTotal();
            $order->save();
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'order' => $order->load('items.product')
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function pay(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();
        $orderId = $args['orderId'];
        $tableId = $args['id'];

        $order = Order::where('id', $orderId)
            ->where('table_id', $tableId)
            ->where('status', 'active')
            ->first();

        if (!$order) {
            $response->getBody()->write(json_encode(['error' => 'Active order not found for this table']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Verificar método de pago
        if (!isset($data['payment_method']) || !in_array($data['payment_method'], ['cash', 'card'])) {
            $response->getBody()->write(json_encode(['error' => 'Valid payment method required (cash or card)']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $order->payment_method = $data['payment_method'];

        // Si es efectivo, verificar el monto entregado
        if ($data['payment_method'] === 'cash' && isset($data['payment_amount'])) {
            $paymentAmount = floatval($data['payment_amount']);
            if ($paymentAmount < $order->total) {
                $response->getBody()->write(json_encode(['error' => 'Payment amount is less than total']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $order->payment_amount = $paymentAmount;
        }

        // Actualizar orden a pagada
        $order->status = 'paid';
        $order->save();

        // Liberar la mesa
        $table = Table::find($tableId);
        $table->status = 'available';
        $table->save();

        $result = [
            'success' => true,
            'order' => $order,
            'change' => ($data['payment_method'] === 'cash') ? ($order->payment_amount - $order->total) : 0
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
