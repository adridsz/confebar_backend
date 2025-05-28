<?php
use PHPUnit\Framework\TestCase;
use App\Models\Order;
use App\Models\OrderItem;

class OrderTest extends TestCase
{
    public function testCalculateTotal()
    {
        $order = new Order();
        $order->setRelation('items', collect([
            new OrderItem(['subtotal' => 10]),
            new OrderItem(['subtotal' => 20]),
            new OrderItem(['subtotal' => 5])
        ]));

        $this->assertEquals(35, $order->calculateTotal());
    }
}