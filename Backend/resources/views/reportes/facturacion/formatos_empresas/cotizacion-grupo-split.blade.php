<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>COTIZACIÓN #{{ $venta->correlativo }} - {{ $venta->nombre_cliente }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .company-info {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .company-contact {
            font-size: 12px;
            color: #333;
        }
        
        .document-title {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            margin: 30px 0;
            text-transform: uppercase;
            text-decoration: underline;
        }
        
        .client-section {
            margin-bottom: 30px;
        }
        
        .client-header {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        
        .client-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .client-details {
            flex: 1;
        }
        
        .client-details p {
            margin-bottom: 5px;
        }
        
        .document-details {
            flex: 1;
            text-align: right;
        }
        
        .document-details p {
            margin-bottom: 5px;
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .products-table th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .products-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        
        .products-table .description {
            text-align: left;
        }
        
        .products-table .quantity,
        .products-table .price,
        .products-table .total {
            text-align: right;
        }
        
        .totals-section {
            margin-left: auto;
            width: 300px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 3px 0;
        }
        
        .total-row.grand-total {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .terms-section {
            margin-top: 40px;
            border-top: 1px solid #000;
            padding-top: 20px;
        }
        
        .terms-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        
        .terms-content {
            margin-bottom: 20px;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .signature-box {
            text-align: center;
            flex: 1;
            margin: 0 20px;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            height: 40px;
            margin-bottom: 10px;
        }
        
        .signature-label {
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .page-number {
            text-align: center;
            margin-top: 30px;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Encabezado de la empresa -->
        <div class="header">
            <div class="company-name">{{ $venta->empresa()->pluck('nombre')->first() }}</div>
            <div class="company-info">{{ $venta->empresa()->pluck('direccion')->first() }}</div>
            <div class="company-info">{{ $venta->empresa()->pluck('municipio')->first() }}, {{ $venta->empresa()->pluck('departamento')->first() }}</div>
            <div class="company-contact">
                Tel: {{ $venta->empresa()->pluck('telefono')->first() }} | 
                Email: {{ $venta->empresa()->pluck('email')->first() ?? 'info@empresa.com' }}
            </div>
        </div>

        <!-- Título del documento -->
        <div class="document-title">COTIZACIÓN</div>

        <!-- Información del cliente -->
        <div class="client-section">
            <div class="client-header">INFORMACIÓN DEL CLIENTE</div>
            <div class="client-info">
                <div class="client-details">
                    <p><strong>Nombre:</strong> {{ $venta->nombre_cliente }}</p>
                    <p><strong>Dirección:</strong> {{ $venta->cliente()->pluck('direccion')->first() }}</p>
                    <p><strong>Ciudad:</strong> {{ $venta->cliente()->pluck('municipio')->first() }}, {{ $venta->cliente()->pluck('departamento')->first() }}</p>
                    <p><strong>Teléfono:</strong> {{ $venta->cliente()->pluck('telefono')->first() }}</p>
                    @if($venta->cliente()->pluck('ncr')->first())
                        <p><strong>NCR/RTN:</strong> {{ $venta->cliente()->pluck('ncr')->first() }}</p>
                    @endif
                </div>
                <div class="document-details">
                    <p><strong>Cotización #:</strong> {{ $venta->correlativo }}</p>
                    <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                    <p><strong>Válido hasta:</strong> {{ \Carbon\Carbon::parse($venta->fecha_expiracion)->format('d/m/Y') }}</p>
                    <p><strong>Vendedor:</strong> {{ $venta->nombre_usuario }}</p>
                </div>
            </div>
        </div>

        <!-- Tabla de productos -->
        <table class="products-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 50%;">DESCRIPCIÓN DEL PRODUCTO/SERVICIO</th>
                    <th style="width: 10%;">CANTIDAD</th>
                    <th style="width: 15%;">PRECIO UNITARIO</th>
                    <th style="width: 20%;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $index => $detalle)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="description">{{ $detalle->nombre_producto }}</td>
                    <td class="quantity">{{ number_format($detalle->cantidad, 0) }}</td>
                    <td class="price">{{ $venta->empresa->currency->currency_symbol ?? '$' }} {{ number_format($detalle->precio, 2) }}</td>
                    <td class="total">{{ $venta->empresa->currency->currency_symbol ?? '$' }} {{ number_format($detalle->total, 2) }}</td>
                </tr>
                @if ($detalle->descuento > 0)
                <tr>
                    <td></td>
                    <td class="description"><em>Descuento aplicado</em></td>
                    <td></td>
                    <td></td>
                    <td class="total">-{{ $venta->empresa->currency->currency_symbol ?? '$' }} {{ number_format($detalle->descuento, 2) }}</td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>

        <!-- Totales -->
        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>{{ $venta->empresa->currency->currency_symbol ?? '$' }} {{ number_format($venta->sub_total, 2) }}</span>
            </div>
            <div class="total-row">
                <span>
                    @if ($venta->empresa->pais == 'Honduras')
                        ISV (15%):
                    @else
                        IVA (13%):
                    @endif
                </span>
                <span>{{ $venta->empresa->currency->currency_symbol ?? '$' }} {{ number_format($venta->iva, 2) }}</span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>{{ $venta->empresa->currency->currency_symbol ?? '$' }} {{ number_format($venta->total, 2) }}</span>
            </div>
        </div>

        <!-- Términos y condiciones -->
        <div class="terms-section">
            <div class="terms-title">TÉRMINOS Y CONDICIONES</div>
            <div class="terms-content">
                @if($venta->observaciones)
                    {!! nl2br(e($venta->observaciones)) !!}
                @else
                    <p>• Esta cotización es válida por 30 días a partir de la fecha de emisión.</p>
                    <p>• Los precios están sujetos a cambios sin previo aviso.</p>
                    <p>• El pago debe realizarse según las condiciones acordadas.</p>
                    <p>• Los productos se entregarán en el plazo establecido.</p>
                @endif
            </div>
        </div>

        <!-- Firmas -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Cliente</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Vendedor</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Autorización</div>
            </div>
        </div>

        <!-- Número de página -->
        <div class="page-number">Página 1 de 1</div>
    </div>
</body>
</html>

