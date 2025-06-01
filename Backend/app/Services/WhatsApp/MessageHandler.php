<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsApp\WhatsAppSession;
use App\Models\Admin\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MessageHandler
{
    /**
     * Manejar mensaje según el estado de la sesión
     */
    public function handle(WhatsAppSession $session, string $message): ?string
    {
        // Normalizar mensaje
        $message = trim(strtolower($message));

        // Comandos globales
        if ($this->isGlobalCommand($message)) {
            return $this->handleGlobalCommand($session, $message);
        }

        // Manejar según estado
        switch ($session->status) {
            case 'pending_code':
                return $this->handlePendingCode($session, $message);
            
            case 'pending_user':
                return $this->handlePendingUser($session, $message);
            
            case 'connected':
                return $this->handleConnectedUser($session, $message);
            
            default:
                return $this->getWelcomeMessage();
        }
    }

    /**
     * Verificar comandos globales
     */
    private function isGlobalCommand(string $message): bool
    {
        $globalCommands = ['hola', 'inicio', 'menu', 'ayuda', 'salir', 'reset'];
        return in_array($message, $globalCommands);
    }

    /**
     * Manejar comandos globales
     */
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

    /**
     * Manejar cuando está esperando código de empresa
     */
    private function handlePendingCode(WhatsAppSession $session, string $message): string
    {
        // Verificar intentos excesivos
        if ($session->shouldBlockForTooManyAttempts()) {
            return "❌ Demasiados intentos fallidos. Escribe 'reset' para reiniciar o contacta soporte.";
        }

        // Intentar conectar con empresa
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

    /**
     * Manejar cuando está esperando email de usuario
     */
    private function handlePendingUser(WhatsAppSession $session, string $message): string
    {
        // Verificar intentos excesivos
        if ($session->shouldBlockForTooManyAttempts()) {
            return "❌ Demasiados intentos fallidos. Escribe 'reset' para reiniciar.";
        }

        // Validar formato de email básico
        if (!filter_var($message, FILTER_VALIDATE_EMAIL)) {
            return "❌ Formato de email inválido.\n\n" .
                   "Por favor, escribe un email válido (ejemplo: usuario@empresa.com)";
        }

        // Intentar conectar con usuario
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

    /**
     * Manejar usuario conectado (menú principal)
     */
    private function handleConnectedUser(WhatsAppSession $session, string $message): string
    {
        // Verificar permisos del usuario
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

    /**
     * Mensaje de bienvenida
     */
    private function getWelcomeMessage(): string
    {
        return "👋 ¡Hola! Bienvenido a *SmartPyme*\n\n" .
               "Soy tu asistente virtual. Para comenzar, necesito que me proporciones el código de tu empresa.\n\n" .
               "Por favor, escribe el código de tu empresa:";
    }

    /**
     * Menú principal personalizado
     */
    private function getMainMenu(WhatsAppSession $session): string
    {
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

    /**
     * Mensaje de ayuda
     */
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

    /**
     * Información de ventas (placeholder)
     */
    private function getSalesInfo(WhatsAppSession $session): string
    {
        // Aquí irían las consultas reales a la base de datos
        return "📈 *Resumen de Ventas*\n\n" .
               "🏢 Empresa: {$session->empresa->nombre}\n" .
               "📅 Período: Hoy\n\n" .
               "• Ventas del día: $0.00\n" .
               "• Ventas del mes: $0.00\n" .
               "• Total de facturas: 0\n\n" .
               "_Funcionalidad en desarrollo..._\n\n" .
               "Escribe 'menu' para volver al menú principal.";
    }

    /**
     * Información de inventario (placeholder)
     */
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

    /**
     * Información de clientes (placeholder)
     */
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

    /**
     * Menú de reportes (placeholder)
     */
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