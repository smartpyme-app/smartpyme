import { AlertasResumenesMock } from './alertas-resumenes.model';

/** ponytail: copy del diseño hasta API de notificaciones financieras */
export const ALERTAS_RESUMENES_CONFIG_MOCK: AlertasResumenesMock = {
  banner: {
    alertasActivas: 14,
    alertasTotal: 15,
    enviarianHoy: 10,
    canalWhatsapp: 8,
    canalCorreo: 11,
    resumenesActivos: 2,
    resumenesTotal: 2,
  },
  avisos: {
    whatsapp: '+503 7712 4589',
    correo: 'beto@ferreteriadonbeto.com',
    horarioOpciones: [
      {
        id: '7-19-urgente',
        label: 'De 7 a. m. a 7 p. m.; las urgentes a cualquier hora',
      },
      {
        id: '8-18',
        label: 'De 8 a. m. a 6 p. m.',
      },
    ],
    horarioId: '7-19-urgente',
  },
  resumenesPeriodicos: [
    {
      id: 'semana',
      titulo: 'Resumen de la semana',
      subtitulo: 'Lunes 7:00 a. m.',
      activo: true,
      whatsapp: true,
      correo: true,
    },
    {
      id: 'mes',
      titulo: 'Resumen del mes',
      subtitulo: 'Al certificar el cierre',
      activo: true,
      whatsapp: false,
      correo: true,
    },
  ],
  vistaPrevia: {
    remitente: 'Lucas - SmartPyme',
    remitenteVerificado: 'cuenta de empresa verificada',
    semanal: {
      titulo: 'Resumen de la semana · 5 – 11 oct\nFerretería Don Beto',
      marcaTiempo: 'lun 7:00 a. m.',
      secciones: [
        {
          titulo: 'La semana pasada',
          lineas: [
            'Ventas: $9,514 (+9.6% vs. semana anterior)',
            'Margen bruto: 35.7%',
            'Cobró: $6,420',
            'Pagó: $10,180',
          ],
        },
        {
          titulo: 'Esta semana',
          lineas: [
            'Pagos comprometidos: $10,317',
            'Cobros esperados: $3,825',
            'Saldo proyectado: $16,156',
            'Punto más bajo (próx. 12 sem.): $4,198 la semana del 26 oct',
          ],
        },
        {
          titulo: 'Para esta semana',
          numerada: true,
          lineas: [
            'Cobrar a Constructora Meléndez ($3,100 vencido)',
            'Cerrar septiembre (4 pendientes en checklist)',
            'Revisar precios de lámina (+9% costo en 6 meses)',
          ],
        },
      ],
      cierre: '¿Quiere el detalle de algo? Respóndame aquí.',
    },
    mensual: {
      titulo: 'Resumen de septiembre 2026\nFerretería Don Beto',
      marcaTiempo: 'al certificar cierre',
      secciones: [
        {
          titulo: 'Resultado del mes',
          lineas: [
            'Ventas netas: $37,910 (−1% vs. presupuesto)',
            'Utilidad operativa: $4,120 (10.9% de ventas)',
            'Margen bruto: 36.1% (−0.3 pts vs. agosto)',
          ],
        },
        {
          titulo: 'Pendientes antes de certificar',
          numerada: true,
          lineas: [
            'Registrar energía y agua (3 sucursales)',
            'Revisar día sin ventas en San Miguel (22 sep)',
            'Clasificar gasto: Seguridad privada (San Miguel)',
          ],
        },
        {
          titulo: 'Impuestos del mes',
          lineas: ['IVA (F-07): $1,064', 'Pago a cuenta (F-14): $663 · vence 14 oct'],
        },
      ],
      cierre: 'Certifique en Suite Financiera o responda aquí si necesita el detalle.',
    },
  },
};
