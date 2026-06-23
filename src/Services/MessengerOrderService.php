<?php

namespace Shudhuiami\MessengerCommerceBot\Services;

use Shudhuiami\MessengerCommerceBot\Contracts\OrderCreator;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\CustomerData;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\OrderRequestData;
use Shudhuiami\MessengerCommerceBot\DataTransferObjects\OrderResult;
use Shudhuiami\MessengerCommerceBot\Models\MessengerConversation;
use Shudhuiami\MessengerCommerceBot\Models\MessengerCustomer;
use Shudhuiami\MessengerCommerceBot\Models\MessengerSession;

class MessengerOrderService
{
    public function __construct(private OrderCreator $creator)
    {
    }

    /**
     * Create an order from a completed messenger session via the host app's
     * own OrderCreator implementation. Idempotent: if the conversation
     * already has an order linked, no new order is created.
     */
    public function createOrder(MessengerCustomer $customer, MessengerSession $session, MessengerConversation $conversation): ?OrderResult
    {
        if ($conversation->order_id) {
            return $this->creator->find($conversation->order_id);
        }

        $productId = $session->product_id;
        $qty = $session->quantity;
        $phone = $session->customer_phone ?? $customer->phone;
        $address = $session->delivery_address;

        if (! $productId || ! $qty || $qty < 1 || ! $phone || ! $address) {
            return null;
        }

        $result = $this->creator->create(
            new CustomerData(psid: $customer->psid, name: $customer->name, email: $customer->email),
            new OrderRequestData(
                productId: (int) $productId,
                quantity: (int) $qty,
                deliveryAddress: $address,
                phone: $phone,
                notes: "Order placed via Facebook Messenger (PSID: {$customer->psid}).",
            ),
        );

        if (! $result) {
            return null;
        }

        $conversation->update(['order_id' => $result->id, 'status' => 'completed']);

        if (! $customer->phone) {
            $customer->update(['phone' => $phone]);
        }

        return $result;
    }
}
