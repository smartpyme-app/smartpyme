import { ContadorLibrosIvaResponse } from '@services/contadores-portal.service';

type InformeItem = ContadorLibrosIvaResponse['grupos'][number]['informes'][number];

export interface LivaDescargaFila {
  etiqueta: string;
  item: InformeItem;
}

export interface LivaModuloDescarga {
  titulo: string;
  filas: LivaDescargaFila[];
}

type ModuloSpec = { titulo: string; filas: { etiqueta: string; clave: string }[] };

const MODULOS_SV: ModuloSpec[] = [
  {
    titulo: 'Compras',
    filas: [
      { etiqueta: 'Libro fiscal', clave: 'compras_libro' },
      { etiqueta: 'Anexo CSV (F-07)', clave: 'compras_anexo' },
    ],
  },
  {
    titulo: 'Ventas a contribuyentes',
    filas: [
      { etiqueta: 'Libro fiscal', clave: 'ventas_contribuyentes_libro' },
      { etiqueta: 'Anexo CSV (F-07)', clave: 'ventas_contribuyentes_anexo' },
    ],
  },
  {
    titulo: 'Ventas a consumidor final',
    filas: [
      { etiqueta: 'Libro fiscal', clave: 'ventas_consumidor_libro' },
      { etiqueta: 'Anexo CSV (F-07)', clave: 'ventas_consumidor_anexo' },
    ],
  },
  {
    titulo: 'Retenciones y percepciones',
    filas: [{ etiqueta: 'Libro fiscal', clave: 'retencion_percepcion_libro' }],
  },
  {
    titulo: 'Sujetos excluidos',
    filas: [{ etiqueta: 'Libro fiscal', clave: 'sujetos_excluidos_libro' }],
  },
  {
    titulo: 'Resumen del período',
    filas: [{ etiqueta: 'Resumen fiscal', clave: 'resumen_iva' }],
  },
  {
    titulo: 'Partida contable',
    filas: [{ etiqueta: 'Partida de IVA', clave: 'partida_iva' }],
  },
];

const MODULOS_CR: ModuloSpec[] = [
  {
    titulo: 'Compras',
    filas: [{ etiqueta: 'Detalle fiscal', clave: 'cr_compras_detalle' }],
  },
  {
    titulo: 'Ventas',
    filas: [{ etiqueta: 'Detalle fiscal', clave: 'cr_ventas_detalle' }],
  },
  {
    titulo: 'Resumen del período',
    filas: [{ etiqueta: 'Resumen fiscal', clave: 'resumen_iva' }],
  },
  {
    titulo: 'Partida contable',
    filas: [{ etiqueta: 'Partida de IVA', clave: 'partida_iva' }],
  },
];

const MODULOS_HD: ModuloSpec[] = [
  {
    titulo: 'Compras',
    filas: [{ etiqueta: 'Libro fiscal', clave: 'compras_libro' }],
  },
  {
    titulo: 'Ventas a contribuyentes',
    filas: [{ etiqueta: 'Libro fiscal', clave: 'ventas_contribuyentes_libro' }],
  },
  {
    titulo: 'Ventas a consumidor final',
    filas: [{ etiqueta: 'Libro fiscal', clave: 'ventas_consumidor_libro' }],
  },
  {
    titulo: 'Retenciones',
    filas: [{ etiqueta: 'Libro fiscal', clave: 'retenciones_libro' }],
  },
  {
    titulo: 'Resumen del período',
    filas: [{ etiqueta: 'Resumen fiscal', clave: 'resumen_iva' }],
  },
  {
    titulo: 'Partida contable',
    filas: [{ etiqueta: 'Partida de IVA', clave: 'partida_iva' }],
  },
];

const MODULOS_GENERAL: ModuloSpec[] = [
  {
    titulo: 'Compras',
    filas: [{ etiqueta: 'Libro fiscal', clave: 'compras_libro' }],
  },
  {
    titulo: 'Ventas',
    filas: [{ etiqueta: 'Libro fiscal', clave: 'ventas_consumidor_libro' }],
  },
  {
    titulo: 'Resumen del período',
    filas: [{ etiqueta: 'Resumen fiscal', clave: 'resumen_iva' }],
  },
  {
    titulo: 'Partida contable',
    filas: [{ etiqueta: 'Partida de IVA', clave: 'partida_iva' }],
  },
];

const SPECS: Record<string, ModuloSpec[]> = {
  sv: MODULOS_SV,
  cr: MODULOS_CR,
  hd: MODULOS_HD,
  general: MODULOS_GENERAL,
};

function mapaInformes(vista: ContadorLibrosIvaResponse): Map<string, InformeItem> {
  const map = new Map<string, InformeItem>();
  for (const grupo of vista.grupos) {
    for (const item of grupo.informes) {
      map.set(item.clave, item);
    }
  }
  return map;
}

/** Agrupa catálogo API por módulo de negocio (compras, ventas, …). */
export function modulosDescargaLibrosIva(vista: ContadorLibrosIvaResponse): LivaModuloDescarga[] {
  const pais = vista.modulo_pais ?? 'sv';
  const specs = SPECS[pais] ?? MODULOS_SV;
  const byClave = mapaInformes(vista);
  const modulos: LivaModuloDescarga[] = [];

  for (const spec of specs) {
    const filas: LivaDescargaFila[] = [];
    for (const f of spec.filas) {
      const item = byClave.get(f.clave);
      if (item) {
        filas.push({ etiqueta: f.etiqueta, item });
      }
    }
    if (filas.length) {
      modulos.push({ titulo: spec.titulo, filas });
    }
  }

  // Informes del backend no mapeados (catálogo futuro)
  const usados = new Set(modulos.flatMap((m) => m.filas.map((f) => f.item.clave)));
  const sueltos: InformeItem[] = [];
  for (const [, item] of byClave) {
    if (!usados.has(item.clave)) {
      sueltos.push(item);
    }
  }
  if (sueltos.length) {
    modulos.push({
      titulo: 'Otros reportes',
      filas: sueltos.map((item) => ({ etiqueta: item.titulo, item })),
    });
  }

  return modulos;
}
