<?php

namespace App\Support\Ventas;

/**
 * Textos demo SP-2386; la empresa puede sobrescribirlos en Mi cuenta.
 */
final class CotizacionFormatoNuevoDefaults
{
    public const COLOR_MARCA = '#0a2f7a';

    public static function carta(): string
    {
        return "Reciba un cordial saludo de parte del equipo de nuestra empresa.\n"
            . "Agradecemos su interés por explorar con nosotros la implementación "
            . "de soluciones para su negocio a través de nuestro sistema.";
    }

    public static function terminos(): string
    {
        return <<<'TXT'
La plataforma se proporciona bajo licencia no exclusiva y no transferible, según el plan de suscripción seleccionado por el cliente. El cliente es responsable del uso adecuado de la plataforma y de la exactitud de los datos ingresados.

Uso del servicio: El cliente es responsable del uso adecuado de la plataforma y de la exactitud de los datos ingresados.

Pagos: Las tarifas establecidas en la cotización deben ser pagadas puntualmente. Los retrasos en el pago pueden llevar a la suspensión o cancelación del servicio.

Disponibilidad del servicio: Se garantiza un 99% de disponibilidad del servicio, excluyendo mantenimientos programados y eventos de fuerza mayor.

Propiedad intelectual: El cliente no podrá realizar ingeniería inversa, descompilar ni modificar la plataforma.

Renovación: Los cobros se efectuarán según la forma de pago elegida. Si no continuará usando el sistema debe notificarse por escrito; se brindará un plazo de 15 días para extraer la información de su cuenta.

Política de reembolsos: No se realizan reembolsos ni devoluciones bajo ninguna circunstancia, salvo acuerdo escrito distinto.
TXT;
    }

    public static function compromisos(): string
    {
        return <<<'TXT'
Brindar capacitaciones y soporte técnico a usuarios de negocios.
Garantizar el correcto funcionamiento de la plataforma con altos estándares de seguridad, disponibilidad y confidencialidad.
Ofrecer acompañamiento durante el proceso de implementación y facturación electrónica cuando aplique.
Brindar documentación de confidencialidad para su firma cuando corresponda.
TXT;
    }

    public static function normalizarColor(?string $color): string
    {
        $color = trim((string) $color);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1) {
            return $color;
        }

        return self::COLOR_MARCA;
    }
}