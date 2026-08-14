import { Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from '@services/api.service';
import { DocumentoImportService } from '@services/compras/documento-import.service';

export interface GastoBulkPrepResult {
  gasto: any;
  detalles: any[];
  varios_items: boolean;
  jsonData?: any;
  error?: string;
}

/**
 * Prepara gastos desde documento electrónico para importación masiva (listado de gastos).
 */
@Injectable({ providedIn: 'root' })
export class GastoJsonBulkService {
  constructor(
    private apiService: ApiService,
    private documentoImportService: DocumentoImportService
  ) {}

  crearGastoBase(): any {
    const user = this.apiService.auth_user();
    return {
      forma_pago: 'Efectivo',
      estado: 'Confirmado',
      tipo_documento: 'Factura',
      tipo: 'Gastos varios',
      concepto: '',
      referencia: '',
      fecha: this.apiService.date(),
      id_empresa: user?.id_empresa,
      id_sucursal: user?.id_sucursal,
      id_usuario: user?.id,
      id_proveedor: null,
      id_categoria: null,
      id_area_empresa: null,
      id_proyecto: null,
      sub_total: 0,
      iva: 0,
      renta_retenida: 0,
      iva_retenido: 0,
      iva_percibido: 0,
      otros_cargos: 0,
      total: 0,
      impuesto: false,
      retencion: false,
      renta: false,
      percepcion: false,
    };
  }

  async prepararGastoDesdeContenido(contenido: string): Promise<GastoBulkPrepResult> {
    try {
      const res = await firstValueFrom(
        this.documentoImportService.importarGasto(contenido.trim())
      );
      const jsonData = res?.dte ?? null;
      const gasto = this.normalizarGasto(res?.gasto || {}, res);
      const built = this.construirDetallesDesdeDte(jsonData, gasto);
      return {
        gasto: built.gasto,
        detalles: built.detalles,
        varios_items: built.varios_items,
        jsonData,
      };
    } catch (e: any) {
      return {
        gasto: this.crearGastoBase(),
        detalles: [],
        varios_items: false,
        error:
          e?.error?.error ||
          e?.message ||
          'No se pudo interpretar el documento.',
      };
    }
  }

  private normalizarGasto(raw: any, res?: any): any {
    const base = this.crearGastoBase();
    const g = { ...base, ...(raw || {}) };

    const user = this.apiService.auth_user();
    g.id_empresa = g.id_empresa || user?.id_empresa;
    g.id_sucursal = g.id_sucursal || user?.id_sucursal;
    g.id_usuario = g.id_usuario || user?.id;

    if (res?.tipo_documento_nombre) {
      g.tipo_documento = res.tipo_documento_nombre;
    }
    if (!g.tipo || String(g.tipo).trim() === '') {
      g.tipo = 'Gastos varios';
    }
    if (!g.forma_pago) {
      g.forma_pago = 'Efectivo';
    }
    if (!g.estado) {
      g.estado = 'Confirmado';
    }
    if (!g.fecha) {
      g.fecha = this.apiService.date();
    }

    g.sub_total = parseFloat(g.sub_total) || 0;
    g.iva = parseFloat(g.iva) || 0;
    g.renta_retenida = parseFloat(g.renta_retenida) || 0;
    g.iva_retenido = parseFloat(g.iva_retenido) || 0;
    g.iva_percibido = parseFloat(g.iva_percibido) || 0;
    g.otros_cargos = parseFloat(g.otros_cargos) || 0;
    g.total = parseFloat(g.total) || 0;
    g.impuesto = !!g.iva || !!g.impuesto;
    g.renta = !!g.renta_retenida || !!g.renta;
    g.retencion = !!g.iva_retenido || !!g.retencion;
    g.percepcion = !!g.iva_percibido || !!g.percepcion;

    if (g.id_proveedor === '' || g.id_proveedor === undefined) {
      g.id_proveedor = null;
    }

    return g;
  }

  /**
   * Si el DTE trae varias líneas, arma varios_items + detalles editables en el modal.
   */
  private construirDetallesDesdeDte(
    dte: any,
    gasto: any
  ): { gasto: any; detalles: any[]; varios_items: boolean } {
    const items = Array.isArray(dte?.cuerpoDocumento) ? dte.cuerpoDocumento : [];
    if (items.length <= 1) {
      return { gasto, detalles: [], varios_items: false };
    }

    const ivaRate = (this.apiService.auth_user()?.empresa?.iva || 13) / 100;
    const tipoDte = String(dte?.identificacion?.tipoDte || '01');
    const tipoLinea = gasto.tipo || 'Gastos varios';

    const detalles = items.map((item: any) => {
      const cant = parseFloat(item.cantidad) || 1;
      const precio = parseFloat(item.precioUni) || 0;
      const montoItem =
        parseFloat(item.ventaGravada) ||
        parseFloat(item.compra) ||
        cant * precio;

      let sub = 0;
      let iva = 0;
      let total = 0;

      if (tipoDte === '03') {
        sub = montoItem;
        iva = sub * ivaRate;
        total = sub + iva;
      } else {
        total = montoItem;
        sub = ivaRate > 0 ? total / (1 + ivaRate) : total;
        iva = total - sub;
      }

      const esGravada = iva > 0;
      return {
        concepto: item.descripcion || '',
        tipo: tipoLinea,
        tipo_gravado: esGravada ? 'gravada' : 'no_sujeta',
        cantidad: cant,
        precio_unitario: precio,
        sub_total: parseFloat(sub.toFixed(2)),
        iva: parseFloat(iva.toFixed(2)),
        renta_retenida: 0,
        iva_retenido: 0,
        iva_percibido: 0,
        total: parseFloat(total.toFixed(2)),
        aplica_iva: esGravada,
        aplica_renta: false,
        aplica_retencion_iva: false,
        aplica_percepcion: false,
        area_empresa: null,
        id_proyecto: gasto.id_proyecto || null,
        id_categoria: null,
      };
    });

    const g = { ...gasto };
    g.concepto = items[0]?.descripcion || g.concepto;
    g.sub_total = parseFloat(
      detalles.reduce((a: number, d: any) => a + (parseFloat(d.sub_total) || 0), 0).toFixed(2)
    );
    g.iva = parseFloat(
      detalles.reduce((a: number, d: any) => a + (parseFloat(d.iva) || 0), 0).toFixed(2)
    );
    g.total = parseFloat(
      detalles.reduce((a: number, d: any) => a + (parseFloat(d.total) || 0), 0).toFixed(2)
    );
    g.impuesto = g.iva > 0;

    return { gasto: g, detalles, varios_items: true };
  }

  /** Payload listo para POST /gasto desde importación masiva. */
  payloadStoreImportacionMasiva(item: {
    gasto: any;
    detalles: any[];
    varios_items: boolean;
  }): object {
    const payload: any = {
      ...item.gasto,
      from_importacion_masiva: true,
    };

    if (payload.id_categoria != null && payload.id_categoria !== '') {
      payload.id_categoria = Number(payload.id_categoria);
    } else {
      payload.id_categoria = null;
    }

    if (item.varios_items && item.detalles?.length) {
      payload.varios_items = true;
      payload.detalles = item.detalles.map((d: any) => {
        const tg = d.tipo_gravado || (d.aplica_iva ? 'gravada' : 'no_sujeta');
        return {
          concepto: d.concepto,
          tipo: (d.tipo && String(d.tipo).trim()) ? d.tipo : 'Gastos varios',
          tipo_gravado: ['gravada', 'exenta', 'no_sujeta'].includes(tg) ? tg : 'gravada',
          cantidad: parseFloat(d.cantidad) || 1,
          precio_unitario: parseFloat(d.precio_unitario) || parseFloat(d.sub_total) || 0,
          sub_total: parseFloat(d.sub_total) || 0,
          iva: parseFloat(d.iva) || 0,
          renta_retenida: parseFloat(d.renta_retenida) || 0,
          iva_retenido: parseFloat(d.iva_retenido) || 0,
          iva_percibido: parseFloat(d.iva_percibido) || 0,
          total: parseFloat(d.total) || 0,
          aplica_iva: tg === 'gravada',
          aplica_renta: !!d.aplica_renta,
          aplica_retencion_iva: !!d.aplica_retencion_iva,
          aplica_percepcion: !!d.aplica_percepcion,
          area_empresa: d.area_empresa || null,
          id_proyecto: d.id_proyecto || null,
          id_categoria:
            d.id_categoria != null && d.id_categoria !== ''
              ? Number(d.id_categoria)
              : null,
        };
      });
    } else {
      payload.varios_items = false;
      delete payload.detalles;
    }

    return payload;
  }
}
