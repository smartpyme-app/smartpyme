import { Injectable } from '@angular/core';
import { ApiService } from '@services/api.service';

/**
 * Prepara compras desde JSON DTE para importación masiva (listado de compras).
 */
@Injectable({ providedIn: 'root' })
export class CompraJsonBulkService {
    constructor(private apiService: ApiService) {}

    private sumAttr(items: any[], attr: string): number {
        if (!items?.length) {
            return 0;
        }
        return items.reduce(
            (a, b) => a + parseFloat(b[attr] != null ? b[attr] : 0),
            0
        );
    }

    getTipoDocumento(tipoDte: string): string {
        const tiposDte: Record<string, string> = {
            '01': 'Factura',
            '03': 'Crédito fiscal',
            '05': 'Nota de débito',
            '06': 'Nota de crédito',
            '07': 'Comprobante de retención',
            '11': 'Factura de exportación',
            '14': 'Sujeto excluido',
        };
        return tiposDte[tipoDte] || 'Factura';
    }

    findProveedor(emisor: any, proveedores: any[]): any | null {
        if (!emisor || !proveedores?.length) {
            return null;
        }
        let p: any = null;
        if (emisor.nit) {
            p = proveedores.find((x) => x.nit === emisor.nit || x.nit == emisor.nit);
            if (p) {
                return p;
            }
        }
        if (emisor.nrc) {
            p = proveedores.find((x) => x.ncr === emisor.nrc || x.ncr == emisor.nrc);
            if (p) {
                return p;
            }
        }
        if (emisor.dui) {
            p = proveedores.find((x) => x.dui === emisor.dui || x.dui == emisor.dui);
            if (p) {
                return p;
            }
        }
        if (emisor.nombre) {
            p = proveedores.find(
                (x) =>
                    x.nombre_empresa === emisor.nombre ||
                    x.nombre_empresa == emisor.nombre ||
                    x.nombre === emisor.nombre ||
                    x.nombre == emisor.nombre
            );
        }
        return p || null;
    }

    crearCompraBase(impuestosCompra: any[]): any {
        const auth = this.apiService.auth_user();
        const imp = (impuestosCompra || []).map((i: any) => ({ ...i, monto: 0 }));
        return {
            fecha: this.apiService.date(),
            fecha_pago: this.apiService.date(),
            forma_pago: 'Efectivo',
            tipo: 'Interna',
            estado: 'Pagada',
            condicion: 'Contado',
            tipo_clasificacion: 'Costo',
            tipo_operacion: 'Gravada',
            tipo_costo_gasto: 'Costo artículos producidos/comprados interno',
            tipo_sector: auth.empresa?.tipo_sector ?? null,
            tipo_documento: 'Factura',
            detalle_banco: '',
            id_proveedor: '',
            detalles: [],
            descuento: 0,
            sub_total: 0,
            percepcion: 0,
            cotizacion: 0,
            iva_retenido: 0,
            iva: 0,
            total_costo: 0,
            total: 0,
            impuestos: imp,
            cobrar_impuestos: auth.empresa?.cobra_iva === 'Si',
            cobrar_percepcion: false,
            id_bodega: auth.id_bodega,
            id_usuario: auth.id,
            id_vendedor: auth.id_empleado,
            id_sucursal: auth.id_sucursal,
            id_empresa: auth.id_empresa,
            credito: false,
            consigna: false,
            retencion: false,
            renta: false,
            observaciones: '',
        };
    }

    /**
     * Asigna la referencia con el correlativo del documento de la sucursal (misma lógica que facturación).
     * El código de generación del DTE no es el correlativo interno; se conserva en observaciones.
     */
    aplicarReferenciaCorrelativo(compra: any, documentos: any[], jsonData?: any): void {
        const doc = (documentos || []).find(
            (d: any) =>
                d.nombre === compra.tipo_documento &&
                String(d.id_sucursal) === String(compra.id_sucursal)
        );
        const codGen = jsonData?.identificacion?.codigoGeneracion;

        if (doc && doc.correlativo != null && String(doc.correlativo).trim() !== '') {
            compra.referencia = doc.correlativo;
        } else if (codGen) {
            compra.referencia = codGen;
        }

        if (codGen && doc) {
            this.anexarObservacionCodigoGeneracionMh(compra, codGen);
        }
    }

    private anexarObservacionCodigoGeneracionMh(compra: any, codGen: string): void {
        const tag = `Código generación MH: ${codGen}`;
        const obs = String(compra.observaciones || '');
        if (!obs.includes(codGen)) {
            compra.observaciones = obs ? `${obs}\n${tag}` : tag;
        }
    }

    /**
     * Varios JSON comparten el mismo correlativo "siguiente" del catálogo; asigna 2401, 2402, 2403…
     * en el orden de `items` (mismo tipo_documento + sucursal). Si no hay documento en catálogo, usa `aplicarReferenciaCorrelativo`.
     *
     * Tras guardar una compra, usar `opts.despuesDeGuardar`: el catálogo en API puede no reflejar aún el incremento;
     * en ese caso las pendientes siguen desde `referenciaGuardada + 1` (no se recalcula solo desde GET).
     */
    aplicarReferenciasSecuencialesImportacion(
        items: { compra: any; estado: string; jsonData?: any }[],
        documentos: any[],
        opts?: {
            despuesDeGuardar?: {
                referenciaGuardada: any;
                tipo_documento: string;
                id_sucursal: any;
            };
        }
    ): void {
        if (opts?.despuesDeGuardar) {
            const dg = opts.despuesDeGuardar;
            const keyDg = `${dg.tipo_documento}-${String(dg.id_sucursal)}`;
            const base = this.parseCorrelativoNumerico(dg.referenciaGuardada);
            if (!isNaN(base)) {
                let siguiente = base + 1;
                for (const it of items) {
                    if (it.estado === 'guardada' || it.estado === 'error') {
                        continue;
                    }
                    const key = `${it.compra.tipo_documento}-${String(it.compra.id_sucursal)}`;
                    if (key !== keyDg) {
                        continue;
                    }
                    it.compra.referencia = siguiente;
                    siguiente += 1;
                    const codGen = it.jsonData?.identificacion?.codigoGeneracion;
                    const doc = (documentos || []).find(
                        (x: any) =>
                            x.nombre === it.compra.tipo_documento &&
                            String(x.id_sucursal) === String(it.compra.id_sucursal)
                    );
                    if (codGen && doc) {
                        this.anexarObservacionCodigoGeneracionMh(it.compra, codGen);
                    }
                }
                return;
            }
        }

        const useSeq = new Map<string, boolean>();
        for (const it of items) {
            if (it.estado === 'guardada' || it.estado === 'error') {
                continue;
            }
            const key = `${it.compra.tipo_documento}-${it.compra.id_sucursal}`;
            if (useSeq.has(key)) {
                continue;
            }
            const d = (documentos || []).find(
                (x: any) =>
                    x.nombre === it.compra.tipo_documento &&
                    String(x.id_sucursal) === String(it.compra.id_sucursal)
            );
            const n = this.parseCorrelativoNumerico(d?.correlativo);
            useSeq.set(
                key,
                !!(d && d.correlativo != null && String(d.correlativo).trim() !== '' && !isNaN(n))
            );
        }

        const counters = new Map<string, number>();
        for (const it of items) {
            if (it.estado === 'guardada' || it.estado === 'error') {
                continue;
            }
            const key = `${it.compra.tipo_documento}-${it.compra.id_sucursal}`;
            if (!useSeq.get(key)) {
                this.aplicarReferenciaCorrelativo(it.compra, documentos, it.jsonData);
                continue;
            }
            if (!counters.has(key)) {
                const d = (documentos || []).find(
                    (x: any) =>
                        x.nombre === it.compra.tipo_documento &&
                        String(x.id_sucursal) === String(it.compra.id_sucursal)
                );
                counters.set(key, this.parseCorrelativoNumerico(d?.correlativo));
            }
            const cur = counters.get(key)!;
            it.compra.referencia = cur;
            counters.set(key, cur + 1);
            const codGen = it.jsonData?.identificacion?.codigoGeneracion;
            const doc = (documentos || []).find(
                (x: any) =>
                    x.nombre === it.compra.tipo_documento &&
                    String(x.id_sucursal) === String(it.compra.id_sucursal)
            );
            if (codGen && doc) {
                this.anexarObservacionCodigoGeneracionMh(it.compra, codGen);
            }
        }
    }

    private parseCorrelativoNumerico(val: any): number {
        if (val == null || val === '') {
            return NaN;
        }
        const digits = String(val).replace(/\D/g, '');
        if (digits.length === 0) {
            return NaN;
        }
        const n = parseInt(digits, 10);
        return isNaN(n) ? NaN : n;
    }

    aplicarCabeceraDte(
        compra: any,
        jsonData: any,
        proveedorRow: any | null,
        documentos: any[] = []
    ): void {
        if (jsonData.identificacion?.fecEmi) {
            compra.fecha = jsonData.identificacion.fecEmi;
            compra.fecha_pago = jsonData.identificacion.fecEmi;
        }
        if (jsonData.identificacion?.tipoDte) {
            compra.tipo_documento = this.getTipoDocumento(jsonData.identificacion.tipoDte) || 'Factura';
        }
        this.aplicarReferenciaCorrelativo(compra, documentos, jsonData);
        if (proveedorRow?.id) {
            compra.id_proveedor = proveedorRow.id;
        }
        if (jsonData.resumen) {
            compra.sub_total =
                jsonData.resumen.subTotal || jsonData.resumen.subTotalVentas || 0;
            compra.total =
                jsonData.resumen.totalPagar || jsonData.resumen.montoTotalOperacion || 0;
            if (jsonData.resumen.tributos) {
                const iva = jsonData.resumen.tributos.find((t: any) => t.codigo === '20');
                if (iva) {
                    compra.iva = iva.valor;
                    compra.cobrar_impuestos = true;
                }
            }
            const percepcion = parseFloat(jsonData.resumen.ivaPerci1) || 0;
            if (percepcion > 0) {
                compra.percepcion = percepcion;
                compra.cobrar_percepcion = true;
                const sello =
                    jsonData.selloRecibido ||
                    jsonData.sello ||
                    (jsonData.documento && jsonData.documento.selloRecibido);
                if (sello) {
                    compra.sello_mh = sello;
                }
            }
        }
    }

    crearDetalleDesdeItem(item: any, producto: any): any {
        const ventaGravada = parseFloat(item.ventaGravada) || 0;
        const ventaExenta = parseFloat(item.ventaExenta) || 0;
        const ventaNoSuj = parseFloat(item.ventaNoSuj) || 0;
        const totalCalculado = ventaGravada + ventaExenta + ventaNoSuj;
        const cantidad = parseFloat(item.cantidad) || 0;
        const precio = parseFloat(item.precioUni) || 0;
        const descuento = parseFloat(item.montoDescu) || 0;
        const totalFinal = totalCalculado > 0 ? totalCalculado : cantidad * precio - descuento;

        const auth = this.apiService.auth_user();
        return {
            id: null,
            id_producto: producto.id,
            nombre: producto.nombre,
            nombre_producto: producto.nombre,
            descripcion: producto.descripcion || item.descripcion,
            cantidad,
            precio,
            costo: producto.costo || 0,
            descuento,
            total: totalFinal,
            total_costo: cantidad * (producto.costo || 0),
            codigo: producto.codigo,
            marca: producto.marca,
            tipo: producto.tipo,
            img: producto.img || 'default-product.png',
            stock: producto.tipo === 'Servicio' ? null : producto.stock || 0,
            inventario_por_lotes: !!producto.inventario_por_lotes,
            lote_id: null,
            porcentaje_impuesto:
                producto.porcentaje_impuesto ?? auth?.empresa?.iva ?? null,
        };
    }

    async resolverLineasDte(cuerpoDocumento: any[]): Promise<{
        detalles: any[];
        noEncontrados: any[];
    }> {
        const detalles: any[] = [];
        const noEncontrados: any[] = [];
        const idEmpresa = this.apiService.auth_user().id_empresa;
        const items = cuerpoDocumento.map((item: any) => ({
            numItem: item.numItem,
            codigo: item.codigo,
            descripcion: item.descripcion,
        }));

        try {
            const res: any = await this.apiService
                .store('productos/resolver-importacion-dte', {
                    id_empresa: idEmpresa,
                    items,
                })
                .toPromise();

            const resultados = Array.isArray(res?.resultados) ? res.resultados : [];

            for (let i = 0; i < cuerpoDocumento.length; i++) {
                const item = cuerpoDocumento[i];
                const productoEncontrado =
                    i < resultados.length ? resultados[i]?.producto ?? null : null;

                if (productoEncontrado) {
                    detalles.push(this.crearDetalleDesdeItem(item, productoEncontrado));
                } else {
                    noEncontrados.push({
                        numItem: item.numItem,
                        codigo: item.codigo,
                        descripcion: item.descripcion,
                        cantidad: item.cantidad,
                        precioUni: item.precioUni,
                        productoSeleccionado: null,
                    });
                }
            }
        } catch {
            for (const item of cuerpoDocumento) {
                noEncontrados.push({
                    numItem: item.numItem,
                    codigo: item.codigo,
                    descripcion: item.descripcion,
                    cantidad: item.cantidad,
                    precioUni: item.precioUni,
                    productoSeleccionado: null,
                });
            }
        }

        return { detalles, noEncontrados };
    }

    recalcularTotales(compra: any, proveedores: any[]): void {
        if (!compra.detalles || !Array.isArray(compra.detalles)) {
            compra.detalles = [];
        }
        if (!compra.impuestos || !Array.isArray(compra.impuestos)) {
            compra.impuestos = [];
        }

        const empresaIva = Number(this.apiService.auth_user()?.empresa?.iva ?? 0);
        const pctIgual = (a: number, b: number) => Math.abs(Number(a) - Number(b)) < 0.01;
        const porcentajesImpuestos = (compra.impuestos || []).map((i: any) => Number(i.porcentaje));

        compra.sub_total = parseFloat(String(this.sumAttr(compra.detalles, 'total'))).toFixed(2);

        const prov = proveedores?.find((p: any) => p.id == compra.id_proveedor);
        if (prov?.tipo_contribuyente === 'Grande') {
            const sub = parseFloat(compra.sub_total) || 0;
            const min = parseFloat(this.apiService.auth_user()?.empresa?.monto_minimo_retencion_iva_gc) || 100;
            compra.retencion = sub > min;
        }

        compra.percepcion = compra.cobrar_percepcion ? compra.sub_total * 0.01 : 0;
        compra.iva_retenido = compra.retencion ? compra.sub_total * 0.01 : 0;
        compra.renta_retenida = compra.renta ? compra.sub_total * 0.1 : 0;

        compra.impuestos.forEach((impuesto: any) => {
            if (compra.cobrar_impuestos) {
                const pctImp = Number(impuesto.porcentaje);
                const monto = compra.detalles
                    .filter((d: any) => {
                        const pctDetalle =
                            d.porcentaje_impuesto != null && d.porcentaje_impuesto !== ''
                                ? Number(d.porcentaje_impuesto)
                                : empresaIva;
                        return pctIgual(pctImp, pctDetalle);
                    })
                    .reduce((sum: number, d: any) => {
                        const ivaLinea =
                            d.iva != null && d.iva !== '' && parseFloat(d.iva) >= 0
                                ? parseFloat(d.iva)
                                : parseFloat(d.total || 0) * (pctImp / 100);
                        return sum + ivaLinea;
                    }, 0);
                impuesto.monto = parseFloat(Number(monto).toFixed(4));
            } else {
                impuesto.monto = 0;
            }
        });

        if (compra.cobrar_impuestos && compra.detalles.length && compra.impuestos.length) {
            const ivaSinAsignar = compra.detalles
                .filter((d: any) => {
                    const pctDetalle =
                        d.porcentaje_impuesto != null && d.porcentaje_impuesto !== ''
                            ? Number(d.porcentaje_impuesto)
                            : empresaIva;
                    return !porcentajesImpuestos.some((p: number) => pctIgual(p, pctDetalle));
                })
                .reduce((sum: number, d: any) => {
                    const ivaLinea =
                        d.iva != null && d.iva !== '' && parseFloat(d.iva) >= 0
                            ? parseFloat(d.iva)
                            : parseFloat(d.total || 0) *
                              (((d.porcentaje_impuesto != null && d.porcentaje_impuesto !== '')
                                  ? Number(d.porcentaje_impuesto)
                                  : empresaIva) /
                                  100);
                    return sum + ivaLinea;
                }, 0);
            if (ivaSinAsignar > 0) {
                const impuestoDestino =
                    compra.impuestos.find((i: any) => pctIgual(Number(i.porcentaje), empresaIva)) ||
                    compra.impuestos[0];
                impuestoDestino.monto = parseFloat(
                    (parseFloat(impuestoDestino.monto) + ivaSinAsignar).toFixed(4)
                );
            }
        }

        compra.iva = parseFloat(String(this.sumAttr(compra.impuestos, 'monto'))).toFixed(2);

        if (compra.cobrar_impuestos && compra.detalles.length) {
            compra.detalles.forEach((d: any) => {
                const totalLinea = parseFloat(d.total || 0);
                const pct =
                    d.porcentaje_impuesto != null && d.porcentaje_impuesto !== ''
                        ? Number(d.porcentaje_impuesto)
                        : empresaIva;
                d.iva = parseFloat((totalLinea * (pct / 100)).toFixed(4));
            });
        }

        compra.descuento = parseFloat(String(this.sumAttr(compra.detalles, 'descuento'))).toFixed(
            2
        );
        compra.total_costo = parseFloat(
            String(this.sumAttr(compra.detalles, 'total_costo'))
        ).toFixed(2);
        compra.total = (
            parseFloat(compra.sub_total) +
            parseFloat(compra.iva) +
            parseFloat(compra.percepcion) -
            parseFloat(compra.iva_retenido) -
            parseFloat(compra.renta_retenida)
        ).toFixed(2);

        compra.tipo_operacion = compra.cobrar_impuestos ? 'Gravada' : 'No Gravada';
    }

    async prepararCompraDesdeJson(
        jsonData: any,
        impuestosCompra: any[],
        proveedores: any[],
        documentos: any[] = []
    ): Promise<{ compra: any; noEncontrados: any[]; error?: string }> {
        if (!jsonData?.identificacion) {
            return {
                compra: this.crearCompraBase(impuestosCompra),
                noEncontrados: [],
                error: 'El JSON no contiene identificacion (DTE).',
            };
        }
        const compra = this.crearCompraBase(impuestosCompra);
        const prov = this.findProveedor(jsonData.emisor, proveedores);
        this.aplicarCabeceraDte(compra, jsonData, prov, documentos);
        const cuerpo = jsonData.cuerpoDocumento || [];
        let noEncontrados: any[] = [];
        if (cuerpo.length) {
            const r = await this.resolverLineasDte(cuerpo);
            compra.detalles = r.detalles;
            noEncontrados = r.noEncontrados;
        }
        this.recalcularTotales(compra, proveedores);
        return { compra, noEncontrados };
    }
}
