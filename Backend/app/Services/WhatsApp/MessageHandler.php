<?php

namespace App\Services\WhatsApp;

use App\Http\Controllers\Api\Chat\ChatController;
use App\Models\WhatsApp\WhatsAppSession;
use App\Models\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Notifications\WhatsAppVerificationCode;
use Carbon\Carbon;

class MessageHandler
{
    protected $chatController;

    public function __construct()
    {
        if (config('services.whatsapp.use_ai', false)) {
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

            case 'pending_verification': // ← NUEVO ESTADO
                return $this->handlePendingVerification($session, $message);

            case 'connected':
                if (config('services.whatsapp.use_ai', false)) {
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
            $requestData = [
                'prompt' => $message,
                'history' => $this->getWhatsAppConversationHistory($session),
                'conversationId' => null,
                'maxTokens' => 300,
                'temperature' => 0.7,
            ];

            $request = new Request();
            $request->replace($requestData);

            $request->setUserResolver(function () use ($session) {
                return $session->usuario;
            });

            $response = $this->chatController->bedrockChat($request);

            $responseData = $response->getData(true);

            if (isset($responseData['message'])) {

                $lucasResponse = $this->processLucasResponseForWhatsApp($responseData['message']);


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

            throw new \Exception('No se recibió respuesta válida de Lucas IA');
        } catch (\Exception $e) {
            Log::error('Error en Lucas IA WhatsApp', [
                'error' => $e->getMessage(),
                'session_id' => $session->id,
                'empresa_id' => $session->id_empresa,
                'usuario_id' => $session->id_usuario
            ]);

            return "🤖 Lucas está temporalmente no disponible.\n\n" .
                $this->handleConnectedUser($session, strtolower($message));
        }
    }

    private function getWhatsAppConversationHistory(WhatsAppSession $session): array
    {
        $messages = WhatsAppMessage::where('whatsapp_number', $session->whatsapp_number)
            ->where('created_at', '>=', now()->subHours(2))
            ->orderBy('created_at', 'asc')
            ->limit(8)
            ->get();

        $history = [];
        foreach ($messages as $msg) {
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
        $cleanResponse = strip_tags($lucasResponse);

        $cleanResponse = html_entity_decode($cleanResponse, ENT_QUOTES, 'UTF-8');

        $cleanResponse = preg_replace('/\s+/', ' ', $cleanResponse);
        $cleanResponse = trim($cleanResponse);

        if (strlen($cleanResponse) > 1500) {
            $cleanResponse = substr($cleanResponse, 0, 1450) . "...\n\n📱 *Respuesta truncada para WhatsApp*\n¿Quieres que continúe?";
        }

        if (!$this->endsWithQuestion($cleanResponse) && !str_contains($cleanResponse, '¿')) {
            $cleanResponse .= "\n\n¿Te ayudo con algo más? 😊";
        }
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


    // private function getMainMenu(WhatsAppSession $session)
    // {
    //     if (config('services.whatsapp.use_ai', false)) {

    //         return $this->getAIMenuWithButtons($session);
    //     }

    //     return $this->getTraditionalTextMenu($session);
    // }

    private function getMainMenu(WhatsAppSession $session, $forceText = false)
    {
        if (config('services.whatsapp.use_ai', false) && !$forceText) {

            return $this->getAIMenuWithButtons($session);
        }

        return $this->getTraditionalTextMenu($session);
    }

    private function getMainMenuAsText(WhatsAppSession $session)
    {
        if (config('services.whatsapp.use_ai', false)) {
            return $this->getAITextMenu($session);
        }

        return $this->getTraditionalTextMenu($session);
    }

    private function getAITextMenu(WhatsAppSession $session)
    {
        $menu = "🤖 *Lucas - Asistente IA*\n\n";
        $menu .= "🏢 Empresa: {$session->empresa->nombre}\n";
        $menu .= "👤 Usuario: {$session->usuario->name}\n\n";
        $menu .= "¿Qué información necesitas?\n\n";

        $menu .= "📈 Escribe *ventas* para ver resumen de ventas\n";
        $menu .= "💰 Escribe *flujo* para ver flujo de efectivo\n";
        $menu .= "📦 Escribe *inventario* para ver estado de inventario\n\n";

        $menu .= "💡 *También puedes preguntarme libremente:*\n";
        $menu .= "• \"¿Cuánto vendimos la semana pasada?\"\n";
        $menu .= "• \"¿Cuáles son mis mejores productos?\"\n";
        $menu .= "• \"¿Tengo facturas vencidas?\"\n\n";
        $menu .= "✨ *Escribe tu pregunta o usa las opciones de arriba*";

        return $menu;
    }

    private function getAIMenuWithButtons(WhatsAppSession $session)
    {
        $bodyText = "🤖 *Lucas - Asistente IA*\n\n";
        $bodyText .= "🏢 Empresa: {$session->empresa->nombre}\n";
        $bodyText .= "👤 Usuario: {$session->usuario->name}\n\n";
        $bodyText .= "¿Qué información necesitas?\n\n";
        $bodyText .= "💡 *También puedes preguntarme libremente:*\n";
        $bodyText .= "• \"¿Cuánto vendimos la semana pasada?\"\n";
        $bodyText .= "• \"¿Cuáles son mis mejores productos?\"\n";
        $bodyText .= "• \"¿Tengo facturas vencidas?\"\n\n";
        $bodyText .= "✨ *Usa los botones o escribe tu pregunta*";

        $buttons = [
            [
                'id' => 'ventas_hoy',
                'title' => '📈 Ventas'
            ],
            [
                'id' => 'flujo_efectivo',
                'title' => '💰 Flujo Efectivo'
            ],
            [
                'id' => 'inventario_estado',
                'title' => '📦 Inventario'
            ]
        ];

        return [
            'type' => 'buttons',
            'body' => $bodyText,
            'buttons' => $buttons
        ];
    }

    private function getTraditionalTextMenu(WhatsAppSession $session)
    {
        $menu = "📊 *{$session->empresa->nombre}*\n";
        $menu .= "Usuario: {$session->usuario->name}\n\n";
        $menu .= "¿Qué información necesitas?\n\n";

        $menu .= "1️⃣ Resumen de ventas\n";
        $menu .= "2️⃣ Estado de inventario\n";
        $menu .= "3️⃣ Información de clientes\n";
        $menu .= "4️⃣ Reportes financieros\n";
        $menu .= "5️⃣ Flujo de efectivo\n";

        $menu .= "\n0️⃣ Mostrar este menú\n";
        $menu .= "🔄 Escribe 'salir' para cerrar sesión";

        return $menu;
    }


    private function isGlobalCommand(string $message): bool
    {
        $globalCommands = ['hola', 'inicio', 'menu', 'ayuda', 'salir', 'reset'];
        return in_array($message, $globalCommands);
    }

    // private function handleGlobalCommand(WhatsAppSession $session, string $message): string
    // {
    //     switch ($message) {
    //         case 'hola':
    //         case 'inicio':
    //         case 'menu':
    //             return $session->isConnected() ? $this->getMainMenu($session) : $this->getWelcomeMessage();

    //         case 'ayuda':
    //             return $this->getHelpMessage();

    //         case 'salir':
    //         case 'reset':
    //             $session->resetConnection();
    //             return "👋 Sesión reiniciada. " . $this->getWelcomeMessage();

    //         default:
    //             return $this->getWelcomeMessage();
    //     }
    // }

    private function handleGlobalCommand(WhatsAppSession $session, string $message): string
    {
        switch ($message) {
            case 'hola':
            case 'inicio':
            case 'menu':
                if ($session->isConnected()) {
                    $menu = $this->getMainMenu($session);
                    if (is_array($menu)) {
                        return $menu['body'];
                    }
                    return $menu;
                }
                return $this->getWelcomeMessage();

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
            // ✨ NUEVA VALIDACIÓN: Verificar que el teléfono del usuario coincida con WhatsApp
            if (!$this->validatePhoneNumber($usuario, $session->whatsapp_number)) {
                Log::warning('Intento de conexión con teléfono no coincidente', [
                    'usuario_id' => $usuario->id,
                    'usuario_telefono' => $usuario->telefono,
                    'whatsapp_number' => $session->whatsapp_number,
                    'empresa_id' => $session->id_empresa
                ]);

                return "❌ *Verificación de seguridad fallida*\n\n" .
                    "El número de teléfono registrado en tu cuenta no coincide con este número de WhatsApp.\n\n" .
                    "📱 *Tu WhatsApp:* {$session->whatsapp_number}\n" .
                    "📋 *Registrado:* " . ($usuario->telefono ?: 'No registrado') . "\n\n" .
                    "Por favor:\n" .
                    "• Contacta a tu administrador para actualizar tu teléfono\n" .
                    "• O usa el WhatsApp registrado en tu cuenta\n\n" .
                    "Escribe 'reset' para intentar con otro email.";
            }

            try {
                // Si la validación del teléfono es exitosa, continúa con el envío del código
                $verificationCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

                $usuario->update([
                    'whatsapp_verification_code' => $verificationCode,
                    'whatsapp_code_expires_at' => Carbon::now()->addMinutes(10),
                    'whatsapp_verified' => false
                ]);

                $usuario->notify(new WhatsAppVerificationCode($verificationCode, $usuario->name));

                $session->update(['status' => 'pending_verification']);

                Log::info('Código de WhatsApp enviado después de validación telefónica', [
                    'usuario_id' => $usuario->id,
                    'empresa_id' => $session->id_empresa,
                    'session_id' => $session->id,
                    'telefono_validado' => true
                ]);

                return "🎉 ¡Perfecto, *{$usuario->name}*!\n\n" .
                    "✅ *Teléfono verificado:* {$session->whatsapp_number}\n\n" .
                    "📧 Te hemos enviado un código de verificación a tu correo:\n" .
                    "*{$usuario->email}*\n\n" .
                    "⏰ El código expirará en 10 minutos.\n\n" .
                    "✍️ Por favor, escribe el código de 6 dígitos que recibiste por correo:";
            } catch (\Exception $e) {
                Log::error('Error enviando código WhatsApp', [
                    'error' => $e->getMessage(),
                    'usuario_id' => $usuario->id ?? null,
                    'session_id' => $session->id
                ]);

                return "❌ Error al enviar el código de verificación.\n\n" .
                    "Por favor, intenta nuevamente o contacta soporte.\n\n" .
                    "Escribe 'reset' para reiniciar.";
            }
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
        // Manejar IDs de botones primero
        $cleanMessage = strtolower(trim($message));
        switch ($message) {
            case 'ventas_hoy':
                return $this->getSalesInfo($session);

            case 'flujo_efectivo':
                return $this->getCashFlowInfo($session);

            case 'inventario_estado':
                return $this->getInventoryInfo($session);
        }


        switch ($cleanMessage) {
            case '📈 ventas':
            case 'ventas':
                return $this->getSalesInfo($session);
            case '💰 flujo efectivo':
            case 'flujo efectivo':
            case 'flujo':
                return $this->getCashFlowInfo($session);
            case '📦 inventario':
            case 'inventario':
                return $this->getInventoryInfo($session);
        }


        switch ($message) {
            case '1':
                return $this->getSalesInfo($session);

            case '2':
                return $this->getInventoryInfo($session);

            case '3':
                return $this->getCustomersInfo($session);

            case '4':
                return $this->getReportsMenu($session);

            case '5':
                return $this->getCashFlowInfo($session);

            case '0':
            case 'menu':
                return $this->getMainMenu($session);

            default:
                return "❌ Opción no válida.\n\n" . $this->getMainMenu($session);
        }

        return "❌ Opción no válida.\n\n" . $this->getMainMenu($session);
    }

    private function getCashFlowInfo(WhatsAppSession $session): string
    {
        return "💰 *Flujo de Efectivo*\n\n" .
            "🏢 Empresa: {$session->empresa->nombre}\n" .
            "📅 Período: Hoy\n\n" .
            "• Ingresos del día: $0.00\n" .
            "• Egresos del día: $0.00\n" .
            "• Flujo neto: $0.00\n\n" .
            "_Funcionalidad en desarrollo..._\n\n" .
            "Escribe 'menu' para volver al menú principal.";
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


    // private function handlePendingVerification(WhatsAppSession $session, string $message): string
    // {
    //     $code = trim($message);

    //     if (!preg_match('/^\d{6}$/', $code)) {
    //         return "❌ Formato de código inválido.\n\n" .
    //             "El código debe tener exactamente 6 dígitos.\n\n" .
    //             "Por favor, escribe el código que recibiste por correo:";
    //     }

    //     $usuario = $session->usuario;
    //     if (
    //         $usuario->whatsapp_verification_code === $code &&
    //         $usuario->whatsapp_code_expires_at > Carbon::now()
    //     ) {

    //         $usuario->update([
    //             'whatsapp_verified' => true,
    //             'whatsapp_verification_code' => null,
    //             'whatsapp_code_expires_at' => null
    //         ]);

    //         $session->update(['status' => 'connected']);

    //         Log::info('Usuario verificado exitosamente en WhatsApp', [
    //             'usuario_id' => $usuario->id,
    //             'empresa_id' => $session->id_empresa,
    //             'session_id' => $session->id
    //         ]);

    //         return "✅ ¡Verificación exitosa!\n\n" .
    //             "🎉 ¡Bienvenido, *{$usuario->name}*!\n\n" .
    //             "🏢 Empresa: {$session->empresa->nombre}\n" .
    //             "👤 Cargo: " . ($usuario->tipo ?? 'Usuario') . "\n\n" .
    //             $this->getMainMenu($session);
    //     }

    //     if ($usuario->whatsapp_code_expires_at < Carbon::now()) {
    //         return "⏰ El código ha expirado.\n\n" .
    //             "Por favor, escribe 'reset' para generar un nuevo código.";
    //     }
    //     return "❌ Código incorrecto.\n\n" .
    //         "Verifica el código de 6 dígitos que recibiste por correo.\n\n" .
    //         "💡 Si no recibiste el correo:\n" .
    //         "• Revisa tu carpeta de spam\n" .
    //         "• Escribe 'reset' para generar un nuevo código";
    // }

    private function handlePendingVerification(WhatsAppSession $session, string $message): string
    {
        $code = trim($message);

        if (!preg_match('/^\d{6}$/', $code)) {
            return "❌ Formato de código inválido.\n\n" .
                "El código debe tener exactamente 6 dígitos.\n\n" .
                "Por favor, escribe el código que recibiste por correo:";
        }

        $usuario = $session->usuario;

        if (
            $usuario->whatsapp_verification_code === $code &&
            $usuario->whatsapp_code_expires_at > Carbon::now()
        ) {

            $usuario->update([
                'whatsapp_verified' => true,
                'whatsapp_verification_code' => null,
                'whatsapp_code_expires_at' => null
            ]);

            $session->update(['status' => 'connected']);

            Log::info('Usuario verificado exitosamente en WhatsApp', [
                'usuario_id' => $usuario->id,
                'empresa_id' => $session->id_empresa,
                'session_id' => $session->id
            ]);


            return "✅ ¡Verificación exitosa!\n\n" .
                "🎉 ¡Bienvenido, *{$usuario->name}*!\n\n" .
                "🏢 Empresa: {$session->empresa->nombre}\n" .
                "👤 Cargo: " . ($usuario->tipo ?? 'Usuario') . "\n\n" .
                $this->getMainMenuAsText($session);
        }

        if ($usuario->whatsapp_code_expires_at < Carbon::now()) {
            return "⏰ El código ha expirado.\n\n" .
                "Por favor, escribe 'reset' para generar un nuevo código.";
        }

        return "❌ Código incorrecto.\n\n" .
            "Verifica el código de 6 dígitos que recibiste por correo.\n\n" .
            "💡 Si no recibiste el correo:\n" .
            "• Revisa tu carpeta de spam\n" .
            "• Escribe 'reset' para generar un nuevo código";
    }



    private function validatePhoneNumber($usuario, string $whatsappNumber): bool
    {
        if (empty($usuario->telefono)) {
            Log::info('Usuario sin teléfono registrado, permitiendo conexión', [
                'usuario_id' => $usuario->id,
                'whatsapp_number' => $whatsappNumber
            ]);
            return true;
        }

        $userPhone = $this->cleanPhoneForComparison($usuario->telefono);
        $whatsappPhone = $this->cleanPhoneForComparison($whatsappNumber);

        Log::info('Validando teléfonos internacionales', [
            'usuario_id' => $usuario->id,
            'telefono_original' => $usuario->telefono,
            'telefono_limpio' => $userPhone,
            'whatsapp_original' => $whatsappNumber,
            'whatsapp_limpio' => $whatsappPhone
        ]);

        // Comparar números limpios (deberían ser idénticos)
        $isValid = $userPhone === $whatsappPhone;

        if ($isValid) {
            Log::info('Teléfonos coinciden exactamente', [
                'usuario_id' => $usuario->id,
                'numero_validado' => $userPhone
            ]);
        } else {
            Log::warning('Teléfonos NO coinciden', [
                'usuario_id' => $usuario->id,
                'usuario_limpio' => $userPhone,
                'whatsapp_limpio' => $whatsappPhone
            ]);
        }

        return $isValid;
    }

    private function cleanPhoneForComparison(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        return $cleaned;
    }
}
