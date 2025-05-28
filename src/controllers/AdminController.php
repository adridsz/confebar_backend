<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminController
{
    public function getProfits(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        $startDate = $queryParams['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $queryParams['end_date'] ?? date('Y-m-d');

        // Asegurar que las fechas estén en formato correcto
        $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
        $endDate = date('Y-m-d 23:59:59', strtotime($endDate));

        // Obtener pedidos pagados en el periodo
        $orders = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Calcular totales
        $totalRevenue = $orders->sum('total');
        $totalOrders = $orders->count();
        $averageOrder = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Calcular métodos de pago
        $cashOrders = $orders->where('payment_method', 'cash');
        $cardOrders = $orders->where('payment_method', 'card');

        $paymentMethods = [
            'cash' => [
                'count' => $cashOrders->count(),
                'amount' => $cashOrders->sum('total')
            ],
            'card' => [
                'count' => $cardOrders->count(),
                'amount' => $cardOrders->sum('total')
            ]
        ];

        // Obtener productos más vendidos
        $orderItems = [];
        foreach ($orders as $order) {
            $items = $order->items()->with('product')->get();
            foreach ($items as $item) {
                $productId = $item->product_id;
                if (!isset($orderItems[$productId])) {
                    $orderItems[$productId] = [
                        'id' => $productId,
                        'name' => $item->product->name,
                        'category' => $item->product->category,
                        'quantity' => 0,
                        'total' => 0
                    ];
                }
                $orderItems[$productId]['quantity'] += $item->quantity;
                $orderItems[$productId]['total'] += $item->subtotal;
            }
        }

        // Ordenar y limitar a los 5 más vendidos
        uasort($orderItems, function ($a, $b) {
            return $b['quantity'] <=> $a['quantity'];
        });

        $topProducts = array_slice(array_values($orderItems), 0, 5);

        $result = [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'average_order' => $averageOrder,
            'payment_methods' => $paymentMethods,
            'top_products' => $topProducts,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getUsers(Request $request, Response $response)
    {
        $users = User::all();
        $response->getBody()->write(json_encode($users));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function createUser(Request $request, Response $response)
    {
        $data = $request->getParsedBody();

        if (!isset($data['username']) || !isset($data['password']) || !isset($data['role'])) {
            $response->getBody()->write(json_encode(['error' => 'Username, password and role are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Verificar si el usuario ya existe
        $existingUser = User::where('username', $data['username'])->first();
        if ($existingUser) {
            $response->getBody()->write(json_encode(['error' => 'Username already exists']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Verificar el rol
        $validRoles = ['dueño', 'gerente', 'camarero'];
        if (!in_array($data['role'], $validRoles)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid role']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Crear el usuario
        $user = new User();
        $user->username = $data['username'];
        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        $user->role = $data['role'];
        $user->save();

        $response->getBody()->write(json_encode([
            'success' => true,
            'user' => $user
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateUser(Request $request, Response $response, array $args)
    {
        $user = User::find($args['id']);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $data = $request->getParsedBody();

        // No permitir cambiar el usuario admin principal
        if ($user->username === 'admin' && isset($data['username']) && $data['username'] !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Cannot change admin username']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Verificar nombre de usuario único
        if (isset($data['username']) && $data['username'] !== $user->username) {
            $existingUser = User::where('username', $data['username'])->first();
            if ($existingUser) {
                $response->getBody()->write(json_encode(['error' => 'Username already exists']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $user->username = $data['username'];
        }

        // Actualizar contraseña si se proporciona
        if (isset($data['password']) && !empty($data['password'])) {
            $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Actualizar rol si se proporciona
        if (isset($data['role'])) {
            $validRoles = ['dueño', 'gerente', 'camarero'];
            if (!in_array($data['role'], $validRoles)) {
                $response->getBody()->write(json_encode(['error' => 'Invalid role']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // No permitir cambiar el rol del admin principal
            if ($user->username === 'admin') {
                $response->getBody()->write(json_encode(['error' => 'Cannot change admin role']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $user->role = $data['role'];
        }

        $user->save();

        $response->getBody()->write(json_encode([
            'success' => true,
            'user' => $user
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function deleteUser(Request $request, Response $response, array $args)
    {
        $user = User::find($args['id']);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // No permitir eliminar al usuario admin principal
        if ($user->username === 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Cannot delete admin user']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $user->delete();

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
