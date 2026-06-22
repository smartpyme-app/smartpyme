import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { Observable, of, from } from 'rxjs';
import { map, catchError, switchMap } from 'rxjs/operators';
import Swal from 'sweetalert2';

export const ORIGEN_STOCK_CONSIGNA_COMPRA = 'consigna_compra';
export const ORIGEN_STOCK_NORMAL = 'normal';

export function esVentaPorConsigna(venta: { consigna?: boolean; estado?: string } | null | undefined): boolean {
    return !!venta?.consigna || venta?.estado === 'Consigna';
}

export function sincronizarFlagConsignaVenta(venta: { consigna?: boolean; estado?: string } | null | undefined): void {
    if (venta && venta.estado === 'Consigna') {
        venta.consigna = true;
    }
}

export function aplicarEstadoConsignaEnVenta(
    venta: { consigna?: boolean; estado?: string; condicion?: string; credito?: boolean } | null | undefined
): void {
    if (!esVentaPorConsigna(venta)) {
        return;
    }
    venta!.estado = 'Consigna';
    venta!.credito = true;
    venta!.condicion = 'Crédito';
}

export function normalizarOrigenStock(origen?: string | null): string {
    return origen === ORIGEN_STOCK_CONSIGNA_COMPRA ? ORIGEN_STOCK_CONSIGNA_COMPRA : ORIGEN_STOCK_NORMAL;
}

export function esOrigenConsignaCompra(origen?: string | null): boolean {
    return origen === ORIGEN_STOCK_CONSIGNA_COMPRA;
}

export interface ResumenStockOrigen {
    consigna_disponible?: number;
    disponible?: number;
    stock_fisico?: number;
    stock_normal?: number;
    tiene_consigna_compra?: boolean;
}

export interface ResumenStockOrigenParams {
    id_producto: number;
    id_bodega: number;
    excluir_venta_id?: number | null;
}

export function obtenerResumenStockOrigen(
    apiService: ApiService,
    params: ResumenStockOrigenParams
): Observable<ResumenStockOrigen> {
    const filtros: Record<string, unknown> = {
        id_producto: params.id_producto,
        id_bodega: params.id_bodega,
    };
    if (params.excluir_venta_id) {
        filtros['excluir_venta_id'] = params.excluir_venta_id;
    }

    return apiService.getAll('productos/consigna-disponible', filtros).pipe(
        map((res: ResumenStockOrigen) => ({
            consigna_disponible: parseFloat(String(res?.consigna_disponible ?? res?.disponible ?? 0)) || 0,
            stock_fisico: parseFloat(String(res?.stock_fisico ?? 0)) || 0,
            stock_normal: parseFloat(String(res?.stock_normal ?? 0)) || 0,
            tiene_consigna_compra: !!res?.tiene_consigna_compra,
        } as Required<Pick<ResumenStockOrigen, 'consigna_disponible' | 'stock_fisico' | 'stock_normal' | 'tiene_consigna_compra'>>)),
        catchError(() =>
            of({
                consigna_disponible: 0,
                stock_fisico: 0,
                stock_normal: 0,
                tiene_consigna_compra: false,
            })
        )
    );
}

export function cantidadProductoEnCarritoPorOrigen(
    detalles: any[] | undefined,
    idProducto: number,
    origenStock: string,
    idPresentacion?: number | null,
    excluirDetalle?: any
): number {
    if (!detalles?.length) {
        return 0;
    }

    return detalles.reduce((sum, det) => {
        if (excluirDetalle && det === excluirDetalle) {
            return sum;
        }
        const mismoProducto = Number(det.id_producto) === Number(idProducto);
        const mismaPresentacion = (det.id_presentacion ?? null) === (idPresentacion ?? null);
        const mismoOrigen = normalizarOrigenStock(det.origen_stock) === normalizarOrigenStock(origenStock);
        if (!mismoProducto || !mismaPresentacion || !mismoOrigen) {
            return sum;
        }
        return sum + (parseFloat(det.cantidad) || 0);
    }, 0);
}

export function preguntarOrigenStockSiAplica(
    apiService: ApiService,
    venta: any,
    producto: any
): Observable<string | null> {
    if (producto.tipo === 'Servicio' || !venta?.id_bodega) {
        return of(ORIGEN_STOCK_NORMAL);
    }

    const idProducto = Number(producto.id_producto ?? producto.id);
    const cantidad = parseFloat(producto.cantidad) || 1;

    return obtenerResumenStockOrigen(apiService, {
        id_producto: idProducto,
        id_bodega: venta.id_bodega,
        excluir_venta_id: venta.id ?? null,
    }).pipe(
        switchMap((resumen) => {
            const consignaDisponible = resumen.consigna_disponible ?? 0;
            if (consignaDisponible <= 0) {
                return of(ORIGEN_STOCK_NORMAL);
            }

            const nombre = producto.nombre || producto.descripcion || 'Producto';
            return from(
                Swal.fire({
                    title: 'Origen del stock',
                    html:
                        `El producto <strong>${nombre}</strong> tiene stock en consigna de compra.<br><br>` +
                        `<strong>Consigna (compra):</strong> ${consignaDisponible}<br>` +
                        `<strong>Inventario normal:</strong> ${resumen.stock_normal ?? 0}<br>` +
                        `<strong>Stock físico total:</strong> ${resumen.stock_fisico ?? 0}<br><br>` +
                        `¿De dónde desea descontar las <strong>${cantidad}</strong> unidad(es)?`,
                    icon: 'question',
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: 'Consigna (compra)',
                    denyButtonText: 'Inventario normal',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        return ORIGEN_STOCK_CONSIGNA_COMPRA;
                    }
                    if (result.isDenied) {
                        return ORIGEN_STOCK_NORMAL;
                    }
                    return null;
                })
            );
        })
    );
}

export function validarCantidadOrigenConsignaCompra(
    apiService: ApiService,
    alertService: AlertService,
    venta: any,
    detalle: any
): Observable<boolean> {
    if (!esOrigenConsignaCompra(detalle.origen_stock) || detalle.tipo === 'Servicio') {
        return of(true);
    }

    const idProducto = Number(detalle.id_producto);
    const cantidad = parseFloat(detalle.cantidad) || 0;

    if (cantidad <= 0) {
        return of(true);
    }

    return obtenerResumenStockOrigen(apiService, {
        id_producto: idProducto,
        id_bodega: venta.id_bodega,
        excluir_venta_id: venta.id ?? null,
    }).pipe(
        map((resumen) => {
            const consignaDisponible = resumen.consigna_disponible ?? 0;
            const enCarrito = cantidadProductoEnCarritoPorOrigen(
                venta.detalles,
                idProducto,
                ORIGEN_STOCK_CONSIGNA_COMPRA,
                detalle.id_presentacion ?? null,
                detalle
            );
            const totalRequerido = enCarrito + cantidad;

            if (totalRequerido > consignaDisponible + 0.0001) {
                alertService.error(
                    `Stock en consigna de compra insuficiente. Disponible: ${consignaDisponible}, solicitado: ${totalRequerido}.`
                );
                return false;
            }

            return true;
        })
    );
}
