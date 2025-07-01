<?php

namespace App\Events;

use App\Models\Authorization\Authorization;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuthorizationApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $authorization;

    public function __construct(Authorization $authorization)
    {
        $this->authorization = $authorization;
    }
}

// app/Events/AuthorizationRejected.php
namespace App\Events;

use App\Models\Authorization\Authorization;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuthorizationRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $authorization;

    public function __construct(Authorization $authorization)
    {
        $this->authorization = $authorization;
    }
}

// app/Listeners/HandleAuthorizationApproved.php
namespace App\Listeners;

use App\Events\AuthorizationApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleAuthorizationApproved implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AuthorizationApproved $event)
    {
        $authorization = $event->authorization;
        
        // Aquí puedes agregar lógica específica según el tipo de autorización
        switch ($authorization->authorizationType->name) {
            case 'purchase_orders_high_amount':
                $this->handlePurchaseOrderApproval($authorization);
                break;
            case 'sales_discount':
                $this->handleSalesDiscountApproval($authorization);
                break;
            // Agregar más casos según necesites
        }
    }

    private function handlePurchaseOrderApproval($authorization)
    {
        // Lógica específica para órdenes de compra
        // Por ejemplo: cambiar estado, enviar notificaciones, etc.
    }

    private function handleSalesDiscountApproval($authorization)
    {
        // Lógica específica para descuentos en ventas
    }
}