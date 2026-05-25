<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <title>SmartPyme - Reporte Automático</title>
</head>
<body style="font-family: arial, helvetica; color: #555; background-color: #eee; margin: 0; padding: 10px 0;" bgcolor="#eee">

    <section style="width: 95%; max-width: 650px; text-align: center; border-radius: 15px; background-color: #fff; margin: auto; padding: 10px;">
        <header>
            <div style="text-align: center; padding: 20px 0;">
                <img width="150" src="https://www.smartpyme.app/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="Logo SmartPyme">
                <div style="margin-top: 15px; border-bottom: 1px solid #cecece;"></div>
            </div>

            @if($datos['tipo_reporte'] === 'ventas-por-vendedor')
                <h2 style="color: #555; margin: 0 0 8px;">Reporte de Ventas por Vendedor</h2>
            @elseif($datos['tipo_reporte'] === 'ventas-por-categoria-vendedor')
                <h2 style="color: #555; margin: 0 0 8px;">Reporte de Ventas por Categoría y Vendedor</h2>
            @elseif($datos['tipo_reporte'] === 'ventas-por-categoria-sucursal')
                <h2 style="color: #555; margin: 0 0 8px;">Reporte de Ventas por Categoría y Sucursal</h2>
            @elseif($datos['tipo_reporte'] === 'estado-financiero-consolidado-sucursales')
                <h2 style="color: #555; margin: 0 0 8px;">Estado Financiero Consolidado por Sucursales</h2>
            @elseif($datos['tipo_reporte'] === 'detalle-ventas-vendedor')
                <h2 style="color: #555; margin: 0 0 8px;">Detalle de Ventas por Vendedor</h2>
            @elseif($datos['tipo_reporte'] === 'detalle-ventas-totales')
                <h2 style="color: #555; margin: 0 0 8px;">Detalle de Ventas Totales</h2>
            @elseif($datos['tipo_reporte'] === 'detalle-ventas-por-producto')
                <h2 style="color: #555; margin: 0 0 8px;">Detalle de Ventas por Producto</h2>
            @else
                <h2 style="color: #555; margin: 0 0 8px;">Reporte Financiero</h2>
            @endif

            <p style="color: #888; font-size: 13px; margin: 0;">Generado automáticamente el {{ date('d/m/Y H:i') }}</p>
        </header>

        <div style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>

        <article style="width: 95%; text-align: justify; margin: auto; color: #555; line-height: 1.6;">
            <p>Estimado/a usuario,</p>

            <p>Adjunto encontrará el reporte solicitado para el período indicado a continuación.</p>

            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left; width: 35%;">Período</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">
                        {{ $datos['fecha_inicio'] ?? $datos['fecha'] }} al {{ $datos['fecha_fin'] ?? $datos['fecha'] }}
                    </td>
                </tr>
                @if(!empty($datos['empresa']))
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Empresa</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $datos['empresa'] }}</td>
                </tr>
                @endif
                @if($datos['tipo_reporte'] === 'ventas-por-vendedor')
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Total de ventas</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $datos['ventasDelDia'] }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Monto total</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">${{ number_format($datos['totalVentas'], 2) }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Vendedores con ventas</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $datos['vendedoresConVentas'] }}</td>
                </tr>
                @endif
            </table>

            @if($datos['tipo_reporte'] === 'ventas-por-vendedor')
                <p>El archivo adjunto contiene el detalle completo de todas las ventas realizadas por cada vendedor, incluyendo productos vendidos, clientes, formas de pago y totales.</p>
            @elseif($datos['tipo_reporte'] === 'ventas-por-categoria-vendedor')
                <p>Este reporte muestra las ventas realizadas por cada vendedor, clasificadas por categoría de productos, con los porcentajes aplicados según la configuración establecida.</p>
            @elseif($datos['tipo_reporte'] === 'ventas-por-categoria-sucursal')
                <p>Este reporte consolida las empresas configuradas con una hoja por empresa. Cada hoja muestra las ventas por sucursal clasificadas en <strong>Productos (100%)</strong> y <strong>Servicios (90%)</strong>.</p>
            @elseif($datos['tipo_reporte'] === 'estado-financiero-consolidado-sucursales')
                <p>Este reporte proporciona una visión global del desempeño financiero de cada sucursal, mostrando ventas, gastos y el balance resultante durante el período especificado.</p>
            @elseif($datos['tipo_reporte'] === 'detalle-ventas-vendedor')
                <p>Este reporte muestra el detalle de ventas realizadas por cada vendedor, incluyendo productos vendidos, clientes, formas de pago y totales.</p>
            @elseif($datos['tipo_reporte'] === 'detalle-ventas-totales')
                <p>Incluye una línea por venta con montos, estado, cliente y demás columnas configuradas en el sistema.</p>
            @elseif($datos['tipo_reporte'] === 'detalle-ventas-por-producto')
                <p>Incluye una fila por cada producto vendido en el período, con cantidades, precios, IVA y sucursal, según la configuración de la empresa.</p>
            @endif

            <p style="background-color: #f8f8f8; padding: 12px 15px; border-left: 4px solid #1775e5; margin: 20px 0; font-size: 14px;">
                <strong>Nota:</strong> Este reporte es generado automáticamente según la programación establecida. La información es confidencial y para uso interno.
            </p>

            <p style="text-align: center; background-color: #f8f8f8; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <strong>¿Necesita ayuda?</strong><br>
                Contacte a nuestro equipo de soporte al <strong>+503 7732 5932</strong>
            </p>
        </article>

        <div style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>

        <footer>
            <p style="margin: 5px;">SmartPyme &copy; {{ date('Y') }}</p>
            <p style="margin: 5px;"><a href="https://smartpyme.sv" target="_blank" style="color: #1775e5; text-decoration: none;">smartpyme.sv</a></p>
            <p style="margin: 10px 5px 5px; font-size: 12px; color: #888;">Este correo y cualquier archivo adjunto son confidenciales y para uso exclusivo del destinatario previsto.</p>
        </footer>
    </section>

</body>
</html>
