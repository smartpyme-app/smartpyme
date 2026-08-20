<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cotización SmartPyme #{{ $venta->correlativo }} - {{ $venta->nombre_cliente }}</title>
    <style>
        * { margin: 0; padding: 0; }
        html, body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #111;
        }
        @page {
            size: letter portrait;
            margin: 0;
        }
        body { padding: 1.35cm 1.7cm 2.7cm 1.7cm; }
        .page-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .page-bg img { width: 100%; height: 100%; display: block; }
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 2.2cm;
        }
        .footer img { width: 100%; height: 2.2cm; display: block; }
        p { margin: 0 0 9px 0; text-align: justify; }
        strong { font-weight: bold; }
        .sans, .label, .section, .meta, .addr, .items, .cap, .qty-title, .note {
            font-family: DejaVu Sans, sans-serif;
        }
        .rule { border: none; border-top: 1px solid #bdbdbd; margin: 8px 0 14px 0; }
        .addr { text-align: right; font-size: 9px; line-height: 1.45; color: #111; }
        .meta { text-align: right; font-size: 10px; margin: 2px 0 16px 0; font-weight: bold;}
        .label { font-weight: bold; font-size: 11px; margin: 0 0 2px 0; text-align: left; }
        .cliente { margin: 0 0 10px 0; text-align: left; }
        .section { font-weight: bold; font-size: 12px; margin: 12px 0 8px 0; text-align: left; page-break-after: avoid; }
        .keep { page-break-inside: avoid; }
        ul { margin: 0 0 10px 18px; padding: 0; }
        li { margin: 0 0 6px 0; text-align: justify; }
        table { width: 100%; border-collapse: collapse; }
        .cover { page-break-after: always; }
        .items { margin: 8px 0 12px 0; }
        .items thead { display: table-header-group; }
        .items th, .cap th {
            background: #0a2f7a;
            color: #fff;
            font-family: DejaVu Sans, sans-serif;
            font-weight: bold;
            font-size: 9.5px;
            text-align: left;
            padding: 8px 10px;
        }
        .items { table-layout: fixed; }
        .items td, .cap td {
            border: 1px solid #c8c8c8;
            padding: 9px 10px;
            font-size: 10px;
            font-family: DejaVu Sans, sans-serif;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .fiscal { font-size: 9px; margin: 0 0 10px 0; text-align: left; line-height: 1.4; }
        .items tr { page-break-inside: avoid; }
        .price { text-align: right; white-space: nowrap; }
        .qty-title { font-weight: bold; font-size: 10px; margin: 6px 0 4px 0; text-align: left; }
        .note { font-size: 9.5px; margin: 8px 0; text-align: left; }
        .obs { margin: 8px 0 12px 0; }
        .terms { margin: 0 0 8px 16px; }
        .terms li { margin-bottom: 7px; }
        .logo { width: 200px; }
    </style>
</head>
<body>
@php
    $imgDataUri = static function (string $file): ?string {
        $abs = public_path('img/' . $file);
        if (! is_file($abs) || ! is_readable($abs)) {
            return null;
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ];
        $bin = @file_get_contents($abs);
        if ($bin === false || $bin === '') {
            return null;
        }

        return 'data:' . ($mimes[$ext] ?? 'image/png') . ';base64,' . base64_encode($bin);
    };
    $logoSrc = $imgDataUri('cotizacion-smartpyme-logo.png');
    $bgSrc = $imgDataUri('bg_smarpyme_cotizacion.png');
    $footerSrc = $imgDataUri('cotizacion-smartpyme-footer.png');
    $simbolo = data_get($venta, 'empresa.currency.currency_symbol', '$');
    $mostrarDesc = $cotizacion_mostrar_descripcion ?? true;
    $lineas = [];
    foreach ($venta->detalles as $detalle) {
        $productoNombre = optional($detalle->producto)->nombre;
        $rawDesc = trim((string) ($detalle->descripcion ?? ''));
        if ($rawDesc === '' && method_exists($detalle, 'getRawOriginal')) {
            $rawDesc = trim((string) $detalle->getRawOriginal('descripcion'));
        }
        $texto = $productoNombre ?: (trim((string) ($detalle->nombre_producto ?? '')) ?: $rawDesc);
        $nombreServicio = $texto;
        $alcance = '';
        if ($mostrarDesc) {
            if ($productoNombre) {
                $nombreServicio = $productoNombre;
                if ($rawDesc !== '' && strcasecmp($rawDesc, $productoNombre) !== 0) {
                    $alcance = $rawDesc;
                } else {
                    $alcance = trim((string) (data_get($detalle, 'producto.descripcion_completa') ?: data_get($detalle, 'producto.descripcion') ?: ''));
                }
            } elseif (preg_match('/^(.{3,70}?):\s*(.+)$/s', $texto, $m)) {
                $nombreServicio = trim($m[1]);
                $alcance = trim($m[2]);
            }
        }
        $lineas[] = [
            'nombre' => $nombreServicio,
            'alcance' => $alcance,
            'precio' => (float) $detalle->precio,
        ];
    }
    $impuestoLabel = (optional($venta->empresa)->pais === 'Honduras') ? 'ISV' : 'IVA';
    $obs = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $venta->observaciones)));
    $pais = (string) data_get($venta, 'empresa.pais');
    $ncr = trim((string) data_get($venta, 'cliente.ncr'));
    $dui = trim((string) data_get($venta, 'cliente.dui'));
    $telCliente = trim((string) data_get($venta, 'cliente.telefono'));
    $dirCliente = trim((string) data_get($venta, 'cliente.direccion'));
    $munCliente = trim((string) data_get($venta, 'cliente.municipio'));
    $depCliente = trim((string) data_get($venta, 'cliente.departamento'));
    $dirClienteCompleta = trim($dirCliente . (($munCliente !== '' || $depCliente !== '') ? ', ' . trim($munCliente . ($munCliente && $depCliente ? ', ' : '') . $depCliente) : ''));
    $pieContacto = 'San Salvador, El Salvador +503 7732-5932 contact@smartpyme.sv www.smartpyme.sv Guatemala +502 5691 2718 Honduras +504 9313-2759';
@endphp

@if($bgSrc)
<div class="page-bg"><img src="{{ $bgSrc }}" alt="{{ $pieContacto }}"></div>
@elseif($footerSrc)
<div class="footer"><img src="{{ $footerSrc }}" alt="{{ $pieContacto }}"></div>
@else
<div class="footer" style="background:#0a2f7a;color:#fff;">
    <table style="height:2.15cm;color:#fff;">
        <tr>
            <td style="color:#fff;font-size:8px;text-align:center;">San Salvador, El Salvador<br>+503 7732-5932</td>
            <td style="color:#fff;font-size:8px;text-align:center;">contact@smartpyme.sv<br>www.smartpyme.sv</td>
            <td style="color:#fff;font-size:8px;text-align:center;">Guatemala<br>+502 5691 2718</td>
            <td style="color:#fff;font-size:8px;text-align:center;">Honduras<br>+504 9313-2759</td>
        </tr>
    </table>
</div>
@endif

<div class="cover">
<table>
    <tr>
        <td style="width: 62%; vertical-align: middle; height: 100px;">
            @if($logoSrc)
                <img class="logo" src="{{ $logoSrc }}" alt="SmartPyme">
            @else
                <span class="sans" style="font-size: 22px; font-weight: bold; color: #1a3a6b;">Smart<span style="color:#2f7de1;">Pyme</span></span>
            @endif
        </td>
        <td class="addr">
            Edificio Colabora loca 1-2<br>
            Paseo General Escalón,<br>
            San Salvador, San Salvador
        </td>
    </tr>
</table>
<hr class="rule">

<p class="meta">
    Cotización #{{ $venta->correlativo }}<br>
    Fecha ( {{ \Carbon\Carbon::parse($venta->fecha)->format('d–m –Y') }} )
    @if(!empty($venta->fecha_expiracion))
        <br>Válido hasta ( {{ \Carbon\Carbon::parse($venta->fecha_expiracion)->format('d–m–Y') }} )
    @endif
</p>

<p class="label">Empresa</p>
<p class="cliente">{{ $venta->nombre_cliente }}</p>
@if($ncr !== '' || $dui !== '' || $telCliente !== '' || $dirClienteCompleta !== '')
<p class="fiscal sans">
    @if($pais === 'Honduras')
        @if($ncr !== '') RTN: {{ $ncr }} @endif
    @else
        @if($ncr !== '') NCR: {{ $ncr }} @endif
        @if($dui !== '') &nbsp; @if($pais === 'El Salvador') DUI: @else Número de identificación: @endif {{ $dui }} @endif
    @endif
    @if($telCliente !== '') &nbsp; Teléfono: {{ $telCliente }} @endif
    @if($dirClienteCompleta !== '')<br>{{ $dirClienteCompleta }}@endif
</p>
@endif
<p class="label">Estimado(a):</p>

<p>Reciban un cordial saludo de parte del equipo de <strong>SmartPyme</strong>. Agradecemos la oportunidad de presentarles nuestra propuesta para la implementación de nuestra plataforma de inteligencia de negocios y gestión empresarial.</p>
<p>SmartPyme es una herramienta digital diseñada para centralizar, controlar y gestionar las actividades clave de su negocio en tiempo real, brindando la flexibilidad y escalabilidad que una empresa en crecimiento requiere.</p>
<p class="section" style="margin-top: 12px;">Presencia Regional y Adaptación Normativa</p>
<p>En SmartPyme nos consolidamos como un aliado estratégico de carácter transfronterizo. Contamos con <strong>Presencia regional e hitos/operaciones activas</strong> en <strong>El Salvador, Guatemala, Honduras y Costa Rica</strong>. Nuestra plataforma está completamente tropicalizada para cada país, adaptándose de manera ágil a los requerimientos fiscales, legales, facturación electrónica y marcos normativos específicos de cada jurisdicción donde opera su empresa.</p>

<hr class="rule">

<p class="section">¿Por qué elegir SmartPyme?</p>
<ul>
    <li><strong>Presencia e integración regional:</strong> Cobertura y respaldo operativo en El Salvador, Guatemala, Honduras y Costa Rica con sistemas ajustados a las leyes tributarias y locales de cada país.</li>
    <li><strong>Todo en una sola plataforma:</strong> Gestione ventas, finanzas, inventarios, compras y reportes de forma totalmente centralizada.</li>
    <li><strong>Acceso total y móvil:</strong> Consulte y controle la información de su negocio desde cualquier lugar y dispositivo, en todo momento.</li>
    <li><strong>Soporte técnico continuo:</strong> Acompañamiento especializado y cercano antes, durante y después de la compra.</li>
    <li><strong>Diseñado para comercio y servicios:</strong> Plataforma altamente amigable, intuitiva y optimizada para la eficiencia operativa de las diferentes empresas.</li>
</ul>
</div>

<p class="section">Propuesta Económica</p>
<p>Presentamos la Propuesta estructurada para la adquisición:</p>

<table class="items">
    <thead>
        <tr>
            <th style="width: 28%;">Servicio</th>
            <th style="width: 52%;">Alcance y Cobertura</th>
            <th style="width: 20%;" class="price">Precio</th>
        </tr>
    </thead>
    <tbody>
        @foreach($lineas as $linea)
            <tr>
                <td>{{ $linea['nombre'] }}</td>
                <td>{!! $linea['alcance'] !== '' ? nl2br(e($linea['alcance'])) : '' !!}</td>
                <td class="price">{{ $simbolo }} {{ number_format($linea['precio'], 2) }} + {{ $impuestoLabel }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if($obs !== '')
<div class="obs">
    <p class="qty-title">Observaciones</p>
    <p>{!! nl2br(e($venta->observaciones)) !!}</p>
</div>
@endif

<p class="note">Nota: Incluidas sin costo adicional (*) Sucursales adicionales al plan, (*) usuarios</p>
<p class="note">El cobro corresponde a la licencia y derecho de uso del software.</p>

<p class="section">Módulos e Integraciones Incluidas</p>
<p>Con el plan cotizado, tendrá acceso completo a las siguientes herramientas y funcionalidades:</p>
<ul>
    <li><strong>Ventas:</strong> Registro de ventas, promociones, devoluciones, preventas, administración de canales de venta, base de datos de clientes, emisión de documentos y categorización.</li>
    <li><strong>Finanzas:</strong> Análisis financiero de resultados, estado de resultados, control integral de cuentas por cobrar y por pagar.</li>
    <li><strong>Inventario:</strong> Control de existencias multi-sucursal, ajustes de kardex, alertas automáticas de punto de reorden y seguimiento de vida útil.</li>
    <li><strong>Compras y Gastos:</strong> Registro centralizado de compras, devoluciones, proveedores y control detallado de gastos operativos.</li>
    <li><strong>Cotizaciones y Paquetes:</strong> Creación rápida de ofertas/cotizaciones y gestión flexible de paquetes de servicios o productos.</li>
    <li><strong>Cumplimiento Tributario Local:</strong> Proyección de libros de IVA y emisión de facturación alineada a las normativas vigentes en cada país de operación.</li>
    <li><strong>Inteligencia de Negocios (BI):</strong> Tableros de control con reportes en tiempo real, análisis del desempeño comercial y sistema de alarmas y recordatorios automáticos.</li>
    <li><strong>Contabilidad y bancos, (En plan Corporativo)</strong> creación de reportes automáticos de balances estados de resultados y mas reportes financieros</li>
    <li><strong>Modulos Extras</strong> como Programa de Lealtad, Gift Card, de Restaurantes, pedidos son funcionabilidades extras que se adquieren por separado</li>
</ul>

<div class="keep">
<p class="section">Nuestros Planes de Implementación y Capacitación</p>
<p>Garantizamos un proceso ágil de adopción tecnológica diseñado a la medida de su equipo de trabajo:</p>
<table class="cap">
    <thead>
        <tr>
            <th style="width: 22%;">Modulo / Plan</th>
            <th style="width: 50%;">Capacitación Incluida</th>
            <th style="width: 28%;">Modalidad</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>SmartPyme</strong></td>
            <td>
                Plan Profesional 3 sesiones dedicadas para el personal administrativo, operativo y de ventas<br>
                Plan Profesional 5 sesiones dedicadas para el personal administrativo, operativo y de ventas
            </td>
            <td>Presencial o Virtual en sede y virtual por sucursal</td>
        </tr>
    </tbody>
</table>
</div>
<ul>
    <li><strong>Material de apoyo:</strong> Entrega de guías de uso y grabaciones íntegras de las sesiones virtuales.</li>
    <li><strong>Soporte técnico continuo:</strong> Atención para dudas o ajustes de lunes a viernes, de 8:00 a.m. a 5:00 p.m.</li>
    <li>La implementación administrativa y sucursales toma de 1 a 2 semanas</li>
</ul>
<p><strong>Las capacitaciones de personal de ventas en sucursales se brindarán de acuerdo a una programación previa al inicio de la integración, y se realizarán de forma virtual</strong></p>

<p class="section">Términos y Condiciones del Servicio</p>
<ol class="terms">
    <li><strong>Uso del Servicio:</strong> Licencia no exclusiva y no transferible. El cliente es responsable de la exactitud de la información cargada y la custodia de sus accesos.</li>
    <li><strong>Pagos y Renovación:</strong> Cobro automático mensual según el plan contratado. En caso de no requerir la renovación, debe notificarse por escrito vía correo electrónico. Se otorgará un plazo de 15 días calendario para el respaldo/descarga de datos antes del cierre definitivo de la cuenta.</li>
    <li><strong>Disponibilidad:</strong> Se garantiza un 99% de disponibilidad de la plataforma (SLA), exceptuando mantenimientos programados o causas de fuerza mayor.</li>
    <li><strong>Propiedad Intelectual y Seguridad:</strong> Queda prohibida la ingeniería inversa o modificación del código. El cliente mantiene la titularidad de su información. En caso de mora, se podrá restringir temporalmente el acceso hasta solventar el estado de cuenta.</li>
    <li><strong>Política de Reembolsos:</strong> No se realizan devoluciones ni reembolsos por cancelaciones anticipadas o falta de uso del sistema.</li>
    <li><strong>Acompañamiento Legal y Tributario:</strong> SmartPyme ofrece asesoría técnica durante el proceso de integración de facturación electrónica o trámites tributarios; sin embargo, las gestiones formales ante los entes reguladores de cada país corresponden al cliente por razones de confidencialidad de credenciales</li>
</ol>

</body>
</html>
