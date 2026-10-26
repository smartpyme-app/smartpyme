import { AlertasCatalogoCategoria } from './alertas-resumenes.model';

/** ponytail: catálogo estático del diseño hasta API de alertas */
export const ALERTAS_CATALOGO_MOCK: AlertasCatalogoCategoria[] = [
  {
    id: 'caja',
    titulo: 'Caja',
    alertas: [
      {
        id: 'colchon',
        titulo: 'Su caja va a bajar del colchón',
        descripcion:
          'Cuando el saldo proyectado de las próximas 4 semanas cae bajo su colchón mínimo. · Al detectarse · máximo 1 vez por semana.',
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'enviaria_hoy',
        config: { tipo: 'sin_ajustes' },
        vistaPreviaWhatsapp:
          'Su caja bajaría a $4,198 la semana del 26 oct – 1 nov — $602 bajo su colchón. Esa semana coinciden nómina neta, rentas de locales (3) y pedido de temporada Holcim. Revise el Flujo de Caja para ver opciones.',
        expandidoInicial: true,
      },
      {
        id: 'efectivo',
        titulo: 'Actualice su efectivo',
        descripcion: 'Si hace más de X días que no actualiza caja + bancos en el modelado.',
        activo: true,
        whatsapp: true,
        correo: false,
        estado: 'sin_novedad',
        config: {
          tipo: 'select',
          prefijo: 'Días sin actualizar:',
          opciones: [
            { id: '3', label: '3 días' },
            { id: '7', label: '7 días' },
            { id: '14', label: '14 días' },
          ],
          valorSelect: '7',
        },
      },
    ],
  },
  {
    id: 'cobros',
    titulo: 'Cobros',
    alertas: [
      {
        id: 'facturas-vencidas',
        titulo: 'Facturas vencidas por cobrar',
        descripcion: 'CxC con más de X días de vencimiento.',
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'enviaria_hoy',
        config: {
          tipo: 'select',
          prefijo: 'Días vencida:',
          opciones: [
            { id: '7', label: '7 días' },
            { id: '15', label: '15 días' },
            { id: '30', label: '30 días' },
          ],
          valorSelect: '15',
        },
      },
      {
        id: 'cliente-sin-compra',
        titulo: 'Cliente con saldo dejó de comprar',
        descripcion:
          'Un cliente a crédito que no compra hace 30+ días y todavía le debe.',
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'enviaria_hoy',
        config: { tipo: 'sin_ajustes' },
      },
    ],
  },
  {
    id: 'pagos',
    titulo: 'Pagos e impuestos',
    alertas: [
      {
        id: 'impuestos',
        titulo: 'Impuestos por vencer',
        descripcion: 'IVA, pago a cuenta y otros vencimientos fiscales.',
        urgente: true,
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'enviaria_hoy',
        config: {
          tipo: 'select',
          prefijo: 'Avisar con:',
          opciones: [
            { id: '1', label: '1 día' },
            { id: '3', label: '3 días' },
            { id: '7', label: '7 días' },
          ],
          valorSelect: '3',
        },
      },
      {
        id: 'pagos-semana',
        titulo: 'Pagos de la semana',
        descripcion:
          'Lo que tiene que pagar esta semana: planilla, proveedores, préstamos e impuestos. · Lunes 7:00 a. m.',
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'enviaria_hoy',
        config: { tipo: 'sin_ajustes' },
      },
    ],
  },
  {
    id: 'ventas',
    titulo: 'Ventas y margen',
    alertas: [
      {
        id: 'ritmo-meta',
        titulo: 'Ventas bajo el ritmo de la meta',
        descripcion:
          'Si el cierre proyectado queda por debajo del presupuesto en más del % indicado. · Días 10 y 20.',
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'sin_novedad',
        config: { tipo: 'variacion_pct', valor: 20 },
      },
      {
        id: 'margen-bajo',
        titulo: 'Su margen bruto bajó',
        descripcion:
          'El margen del mes cae más del % indicado vs. promedio 6 meses. · Días 10 y 20.',
        activo: true,
        whatsapp: false,
        correo: true,
        estado: 'sin_novedad',
        config: { tipo: 'variacion_pct', valor: 20 },
      },
      {
        id: 'costo-precio',
        titulo: 'Costo sube y el precio no',
        descripcion:
          'Un producto sube de costo en 6 meses más del % indicado sobre el precio de venta. · Al cierre.',
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'sin_novedad',
        config: { tipo: 'variacion_pct', valor: 20 },
      },
    ],
  },
  {
    id: 'gastos',
    titulo: 'Gastos',
    alertas: [
      {
        id: 'gasto-anormal',
        titulo: 'Gasto fuera de lo normal',
        descripcion:
          'Una categoría o devolución de sucursal sube más del % indicado vs. promedio 6 meses. · Al cierre.',
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'enviaria_hoy',
        config: { tipo: 'variacion_pct', valor: 20 },
      },
      {
        id: 'gasto-recurrente',
        titulo: 'Gasto recurrente nuevo',
        descripcion:
          'Un pago se repite 3 meses y no está en el calendario de costos fijos. · Al detectarse.',
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'enviaria_hoy',
        config: { tipo: 'sin_ajustes' },
      },
    ],
  },
  {
    id: 'inventario',
    titulo: 'Inventario',
    alertas: [
      {
        id: 'top-agotarse',
        titulo: 'Producto top 20 por agotarse',
        descripcion:
          'Uno de los 20 productos con más ventas tiene menos de X días de inventario al ritmo actual. · Diario 7:00 a. m.',
        urgente: true,
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'enviaria_hoy',
        config: {
          tipo: 'select',
          prefijo: 'Le quedan menos de:',
          opciones: [
            { id: '3', label: '3 días' },
            { id: '7', label: '7 días' },
            { id: '14', label: '14 días' },
          ],
          valorSelect: '7',
        },
      },
      {
        id: 'sin-rotacion',
        titulo: 'Inventario sin rotación',
        descripcion:
          'Productos sin venta en los días indicados. · Primer lunes del mes.',
        activo: false,
        whatsapp: false,
        correo: true,
        estado: 'apagada',
        config: {
          tipo: 'select',
          prefijo: 'Sin venta:',
          opciones: [
            { id: '30', label: '30 días' },
            { id: '60', label: '60 días' },
            { id: '90', label: '90 días' },
          ],
          valorSelect: '60',
        },
      },
    ],
  },
  {
    id: 'sucursales',
    titulo: 'Sucursales',
    alertas: [
      {
        id: 'sucursal-costos',
        titulo: 'Sucursal que no cubre sus costos',
        descripcion: 'Sucursal con contribución negativa en el mes cerrado. · Al cierre.',
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'enviaria_hoy',
        config: { tipo: 'sin_ajustes' },
      },
    ],
  },
  {
    id: 'cierre',
    titulo: 'Cierre de mes',
    alertas: [
      {
        id: 'recordatorio-cierre',
        titulo: 'Recordatorio de cierre',
        descripcion: 'El mes anterior sigue abierto con pendientes. · Días 3 y 7.',
        activo: true,
        whatsapp: true,
        correo: true,
        estado: 'enviaria_hoy',
        config: { tipo: 'sin_ajustes' },
      },
    ],
  },
];
