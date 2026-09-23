<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\WhatsApp\MessageHandler;
use ReflectionMethod;
use Tests\TestCase;

class LucasWhatsAppFormatTest extends TestCase
{
    public function test_conserva_marcado_de_whatsapp_y_trunca_el_exceso(): void
    {
        config(['services.whatsapp.use_ai' => false]);
        $handler = new MessageHandler();
        $method = new ReflectionMethod(MessageHandler::class, 'processLucasResponseForWhatsApp');
        $method->setAccessible(true);

        $text = "*Ventas*\n\n• Hoy: 10";
        $this->assertSame($text, $method->invoke($handler, "  {$text}  "));

        $cut = $method->invoke($handler, str_repeat('a', 1600));
        $this->assertStringStartsWith(str_repeat('a', 1450), $cut);
        $this->assertStringContainsString('*Respuesta truncada para WhatsApp*', $cut);
        $this->assertStringNotContainsString('<p>', $cut);
    }
}
