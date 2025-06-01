<?php

namespace App\Services\WhatsApp;

use App\Http\Controllers\Api\Chat\ChatController;
use App\Models\WhatsApp\WhatsAppSession;
use App\Models\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class MessageHandler
{
    protected $chatController;

    public function __construct()
    {
        if (config('whatsapp.use_ai', false)) {
            $this->chatController = app(ChatController::class);
        }
    }
    public function handle(WhatsAppSession $session, string $message): ?string
    {
        $message = trim($message);

        if ($this->isGlobalCommand(strtolower($message))) {
            return $this->handleGlobalCommand($session, $message);
        }

        switch ($session->status) {
            case 'pending_code':
                return $this->handlePendingCode($session, $message);

            case 'pending_user':
                return $this->handlePendingUser($session, $message);

            case 'connected':
                if (config('whatsapp.use_ai', false)) {
                    return $this->handleWithLucasIA($session, $message);
                } else {
                    return $this->handleConnectedUser($session, strtolower($message));
                }

            default:
                return $this->getWelcomeMessage();
        }
    }


    private function handleWithLucasIA(WhatsAppSession $session, string $message): string
    {
        try {
            // 1. Simular un Request como si viniera del frontend
            $requestData = [
                'prompt' => $message,
                'history' => $this->getWhatsAppConversationHistory($session),
                'conversationId' => null, // WhatsApp no maneja conversationId
                'maxTokens' => 300, // Respuestas más cortas para WhatsApp
                'temperature' => 0.7,
            ];

            // 2. Crear un Request mock para el ChatController
            $request = new Request();
            $request->replace($requestData);

            // 3. Simular usuario autenticado
            $request->setUserResolver(function () use ($session) {
                return $session->usuario;
            });

            // 4. Llamar directamente al método bedrockChat de tu ChatController
            $response = $this->chatController->bedrockChat($request);

            // 5. Extraer la respuesta del JSON
            $responseData = $response->getData(true);

            if (isset($responseData['message'])) {
                // 6. Procesar respuesta para WhatsApp
                $lucasResponse = $this->processLucasResponseForWhatsApp($responseData['message']);

                // 7. Guardar interacción en WhatsApp messages
                WhatsAppMessage::logAIInteraction(
                    $session->whatsapp_number,
                    $message,
                    $lucasResponse,
                    $session,
                    [
                        'ai_model' => $responseData['modelUsed'] ?? 'bedrock-haiku',
                        'suggestions' => $responseData['suggestions'] ?? []
                    ]
                );

                return $lucasResponse;
            }

            // Fallback si no hay mensaje en la respuesta
            throw new \Exception('No se recibió respuesta válida de Lucas IA');
        } catch (\Exception $e) {
            Log::error('Error en Lucas IA WhatsApp', [
                'error' => $e->getMessage(),
                'session_id' => $session->id,
                'empresa_id' => $session->id_empresa,
                'usuario_id' => $session->id_usuario
            ]);

            // Fallback al sistema de menú tradicional
            return "🤖 Lucas está temporalmente no disponible.\n\n" .
                $this->handleConnectedUser($session, strtolower($message));
        }
    }

    private function getWhatsAppConversationHistory(WhatsAppSession $session): array
    {
        $messages = WhatsAppMessage::where('whatsapp_number', $session->whatsapp_number)
            ->where('created_at', '>=', now()->subHours(2)) // Solo últimas 2 horas
            ->orderBy('created_at', 'asc')
            ->limit(8) // Máximo 8 mensajes para no sobrecargar
            ->get();

        $history = [];
        foreach ($messages as $msg) {
            // Limpiar contenido de HTML si es respuesta de IA
            $content = $msg->is_bot_response ? strip_tags($msg->message_content) : $msg->message_content;

            $history[] = [
                'role' => $msg->message_type === 'incoming' ? 'user' : 'assistant',
                'content' => $content
            ];
        }

        return $history;
    }


    private function processLucasResponseForWhatsApp(string $lucasResponse): string
    {
        // 1. Remover HTML y mantener solo texto
        $cleanResponse = strip_tags($lucasResponse);

        // 2. Convertir entidades HTML
        $cleanResponse = html_entity_decode($cleanResponse, ENT_QUOTES, 'UTF-8');

        // 3. Limpiar espacios excesivos
        $cleanResponse = preg_replace('/\s+/', ' ', $cleanResponse);
        $cleanResponse = trim($cleanResponse);

        // 4. Limitar longitud para WhatsApp
        if (strlen($cleanResponse) > 1500) {
            $cleanResponse = substr($cleanResponse, 0, 1450) . "...\n\n📱 *Respuesta truncada para WhatsApp*\n¿Quieres que continúe?";
        }

        // 5. Agregar toque personalizado para WhatsApp
        if (!$this->endsWithQuestion($cleanResponse) && !str_contains($cleanResponse, '¿')) {
            $cleanResponse .= "\n\n¿Te ayudo con algo más? 😊";
        }

        // 6. Agregar emoji de Lucas si no tiene emojis
        if (!preg_match('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]/u', $cleanResponse)) {
            $cleanResponse = "🤖 " . $cleanResponse;
        }

        return $cleanResponse;
    }

    private function endsWithQuestion(string $text): bool
    {
        return str_ends_with(trim($text), '?');
    }

    private function getWelcomeMessage(): string
    {
        Log::info('whatsapp.use_ai', [config('services.whatsapp.use_ai')]);
        if (config('services.whatsapp.use_ai', false)) {
            return "👋 ¡Hola! Soy *Lucas*, tu asistente financiero de SmartPyme.\n\n" .
                "Para comenzar, necesito que me proporciones el código de tu empresa.\n\n" .
                "Por favor, escribe el código de tu empresa:";
        }

        return "👋 ¡Hola! Bienvenido a *SmartPyme*\n\n" .
            "Soy tu asistente virtual. Para comenzar, necesito que me proporciones el código de tu empresa.\n\n" .
            "Por favor, escribe el código de tu empresa:";
    }


    private function getMainMenu(WhatsAppSession $session): string
    {
        if (config('services.whatsapp.use_ai', false)) {
            return "🤖 *Lucas - Asistente IA*\n\n" .
                "🏢 Empresa: {$session->empresa->nombre}\n" .
                "👤 Usuario: {$session->usuario->name}\n\n" .
                "¡Hola! Puedes preguntarme cualquier cosa sobre tu empresa:\n\n" .
                "💡 *Ejemplos:*\n" .
                "• ¿Cuánto vendimos ayer?\n" .
                "• ¿Cuál es mi flujo de efectivo?\n" .
                "• ¿Qué productos están en stock bajo?\n" .
                "• ¿Cuáles son mis mejores clientes?\n\n" .
                "✨ Solo pregunta en lenguaje natural y yo te ayudo.";
        }

        $permissions = $session->usuario->getWhatsAppPermissions();
        $menu = "📊 *{$session->empresa->nombre}*\n";
        $menu .= "Usuario: {$session->usuario->name}\n\n";
        $menu .= "¿Qué información necesitas?\n\n";

        $optionNumber = 1;
        if ($permissions['view_sales']) {
            $menu .= "{$optionNumber}️⃣ Resumen de ventas\n";
            $optionNumber++;
        }
        if ($permissions['view_inventory']) {
            $menu .= "{$optionNumber}️⃣ Estado de inventario\n";
            $optionNumber++;
        }
        if ($permissions['view_customers']) {
            $menu .= "{$optionNumber}️⃣ Información de clientes\n";
            $optionNumber++;
        }
        if ($permissions['view_reports']) {
            $menu .= "{$optionNumber}️⃣ Reportes\n";
            $optionNumber++;
        }

        $menu .= "\n0️⃣ Mostrar este menú\n";
        $menu .= "🔄 Escribe 'salir' para cerrar sesión";

        return $menu;
    }





    private function isGlobalCommand(string $message): bool
    {
        $globalCommands = ['hola', 'inicio', 'menu', 'ayuda', 'salir', 'reset'];
        return in_array($message, $globalCommands);
    }

    private function handleGlobalCommand(WhatsAppSession $session, string $message): string
    {
        switch ($message) {
            case 'hola':
            case 'inicio':
            case 'menu':
                return $session->isConnected() ? $this->getMainMenu($session) : $this->getWelcomeMessage();

            case 'ayuda':
                return $this->getHelpMessage();

            case 'salir':
            case 'reset':
                $session->resetConnection();
                return "👋 Sesión reiniciada. " . $this->getWelcomeMessage();

            default:
                return $this->getWelcomeMessage();
        }
    }

    private function handlePendingCode(WhatsAppSession $session, string $message): string
    {
        if ($session->shouldBlockForTooManyAttempts()) {
            return "❌ Demasiados intentos fallidos. Escribe 'reset' para reiniciar o contacta soporte.";
        }

        $empresa = $session->connectToCompany($message);

        if ($empresa) {
            return "✅ ¡Perfecto! Te has conectado a: *{$empresa->nombre}*\n\n" .
                "Ahora necesito verificar tu identidad.\n" .
                "Por favor, escribe tu email registrado en el sistema:";
        }

        $attempts = $session->code_attempts;
        $remaining = 5 - $attempts;

        return "❌ Código de empresa no encontrado.\n\n" .
            "Intentos restantes: {$remaining}\n\n" .
            "Por favor, verifica el código e intenta nuevamente.\n" .
            "Escribe 'ayuda' si necesitas asistencia.";
    }

    private function handlePendingUser(WhatsAppSession $session, string $message): string
    {

        if ($session->shouldBlockForTooManyAttempts()) {
            return "❌ Demasiados intentos fallidos. Escribe 'reset' para reiniciar.";
        }

        if (!filter_var($message, FILTER_VALIDATE_EMAIL)) {
            return "❌ Formato de email inválido.\n\n" .
                "Por favor, escribe un email válido (ejemplo: usuario@empresa.com)";
        }
        $usuario = $session->connectToUser($message);

        if ($usuario) {
            return "🎉 ¡Bienvenido, *{$usuario->name}*!\n\n" .
                "Empresa: {$session->empresa->nombre}\n" .
                "Cargo: " . ($usuario->tipo ?? 'Usuario') . "\n\n" .
                $this->getMainMenu($session);
        }

        $attempts = $session->user_attempts;
        $remaining = 5 - $attempts;

        return "❌ Email no encontrado en la empresa {$session->empresa->nombre}.\n\n" .
            "Intentos restantes: {$remaining}\n\n" .
            "Verifica que:\n" .
            "• El email esté registrado en el sistema\n" .
            "• Tu cuenta esté activa\n\n" .
            "Escribe 'empresa' para cambiar de empresa.";
    }

    private function handleConnectedUser(WhatsAppSession $session, string $message): string
    {

        $permissions = $session->usuario->getWhatsAppPermissions();

        switch ($message) {
            case '1':
                if (!$permissions['view_sales']) {
                    return "❌ No tienes permisos para ver información de ventas.";
                }
                return $this->getSalesInfo($session);

            case '2':
                if (!$permissions['view_inventory']) {
                    return "❌ No tienes permisos para ver información de inventario.";
                }
                return $this->getInventoryInfo($session);

            case '3':
                if (!$permissions['view_customers']) {
                    return "❌ No tienes permisos para ver información de clientes.";
                }
                return $this->getCustomersInfo($session);

            case '4':
                if (!$permissions['view_reports']) {
                    return "❌ No tienes permisos para ver reportes.";
                }
                return $this->getReportsMenu($session);

            case '0':
            case 'menu':
                return $this->getMainMenu($session);

            default:
                return "❌ Opción no válida.\n\n" . $this->getMainMenu($session);
        }
    }


    private function getHelpMessage(): string
    {
        return "🆘 *Ayuda - SmartPyme WhatsApp*\n\n" .
            "*Comandos disponibles:*\n" .
            "• `hola` - Mostrar menú principal\n" .
            "• `menu` - Mostrar opciones\n" .
            "• `ayuda` - Mostrar esta ayuda\n" .
            "• `salir` - Cerrar sesión\n" .
            "• `reset` - Reiniciar completamente\n\n" .
            "*¿Necesitas más ayuda?*\n" .
            "Contacta a tu administrador del sistema.";
    }

    private function getSalesInfo(WhatsAppSession $session): string
    {
        return "📈 *Resumen de Ventas*\n\n" .
            "🏢 Empresa: {$session->empresa->nombre}\n" .
            "📅 Período: Hoy\n\n" .
            "• Ventas del día: $0.00\n" .
            "• Ventas del mes: $0.00\n" .
            "• Total de facturas: 0\n\n" .
            "_Funcionalidad en desarrollo..._\n\n" .
            "Escribe 'menu' para volver al menú principal.";
    }

    private function getInventoryInfo(WhatsAppSession $session): string
    {
        return "📦 *Estado de Inventario*\n\n" .
            "🏢 Empresa: {$session->empresa->nombre}\n\n" .
            "• Total productos: 0\n" .
            "• Productos con stock bajo: 0\n" .
            "• Productos sin stock: 0\n\n" .
            "_Funcionalidad en desarrollo..._\n\n" .
            "Escribe 'menu' para volver al menú principal.";
    }

    private function getCustomersInfo(WhatsAppSession $session): string
    {
        return "👥 *Información de Clientes*\n\n" .
            "🏢 Empresa: {$session->empresa->nombre}\n\n" .
            "• Total clientes: 0\n" .
            "• Clientes nuevos (mes): 0\n" .
            "• Clientes activos: 0\n\n" .
            "_Funcionalidad en desarrollo..._\n\n" .
            "Escribe 'menu' para volver al menú principal.";
    }

    private function getReportsMenu(WhatsAppSession $session): string
    {
        return "📊 *Reportes Disponibles*\n\n" .
            "🏢 Empresa: {$session->empresa->nombre}\n\n" .
            "• Reporte de ventas\n" .
            "• Reporte de inventario\n" .
            "• Reporte de clientes\n\n" .
            "_Funcionalidad en desarrollo..._\n\n" .
            "Escribe 'menu' para volver al menú principal.";
    }
}
