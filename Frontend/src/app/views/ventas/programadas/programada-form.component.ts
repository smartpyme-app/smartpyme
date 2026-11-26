import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { BuscadorClientesComponent } from '@shared/parts/buscador-clientes/buscador-clientes.component';
import { CrearClienteComponent } from '@shared/modals/crear-cliente/crear-cliente.component';
import { VentaDetallesV2Component } from '@views/ventas/facturacion/facturacion-tienda-v2/detalles/venta-detalles-v2.component';
import { nombresDocumentosVentaNormales } from '@views/ventas/documentos/documento-nombre-options';
import { DisplayNombreImpuestoPipe } from '@pipes/display-nombre-impuesto.pipe';
import {
    acumularImpuestosVentaConCierreResidual,
    calcularMontosLineaDetalle,
    redondearMoneda,
    sincronizarTipoGravadoPorCobroIva,
    sumarSubTotalEncabezadoVenta,
    sumarTotalEncabezadoVenta,
} from '@utils/impuestos-venta.util';

@Component({
    selector: 'app-programada-form',
    templateUrl: './programada-form.component.html',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        FormsModule,
        CurrencyPipe,
        DisplayNombreImpuestoPipe,
        BuscadorClientesComponent,
        CrearClienteComponent,
        VentaDetallesV2Component,
    ],
    // ponytail: Default CD como facturación v2; OnPush ocultaba resultados del buscador hasta el siguiente tecleo.
})
export class ProgramadaFormComponent extends BaseComponent implements OnInit {
    public documentos: any[] = [];
    public usuarios: any[] = [];
    public bodegas: any[] = [];
    public formaPagos: any[] = [];
    /** Catálogo de impuestos de venta (igual que facturación v2). */
    public impuestosCatalogo: any[] = [];
    public saving = false;
    public loading = false;
    public editId: number | null = null;
    public cliente: any = { nombre: '' };

    /** Shell compatible con venta-detalles-v2 / buscador de productos. */
    public venta: any = {
        detalles: [],
        impuestos: [],
        id_bodega: null,
        id_sucursal: null,
        id_cliente: null,
        id_usuario: null,
        cliente: { nombre: '' },
        observaciones: '',
        cotizacion: 0,
        cobrar_impuestos: true,
        nombre_documento: '',
        sub_total: 0,
        descuento: 0,
        gravada: 0,
        exenta: 0,
        no_sujeta: 0,
        iva: 0,
        total: 0,
    };

    public readonly horas12 = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
    public readonly minutos = Array.from({ length: 60 }, (_, i) => i);

    public form = {
        id_documento: null as number | null,
        frecuencia: 'mensual' as 'mensual' | 'anual',
        /** YYYY-MM-DD de la próxima corrida (también define el día del ciclo). */
        fecha_proxima: '',
        dia_ejecucion: 1,
        hora12: 8,
        minuto: 0,
        ampm: 'AM' as 'AM' | 'PM',
        forma_pago: 'Efectivo',
        condicion: 'Contado',
        activo: true,
        emitir_automatico: false,
        enviar_email: false,
        max_ejecuciones: null as number | null,
    };

    constructor(
        public apiService: ApiService,
        private alertService: AlertService,
        private route: ActivatedRoute,
        private router: Router,
    ) {
        super();
    }

    ngOnInit(): void {
        const user = this.apiService.auth_user();
        this.venta.id_bodega = user?.id_bodega ?? null;
        this.venta.id_sucursal = user?.id_sucursal ?? null;
        this.venta.id_usuario = user?.id ?? null;

        const idParam = this.route.snapshot.paramMap.get('id');
        this.editId = idParam ? Number(idParam) : null;

        // Igual que facturación v2 al iniciar.
        this.venta.cobrar_impuestos =
            this.apiService.auth_user()?.empresa?.cobra_iva === 'Si';

        this.cargarBodegas();
        this.cargarUsuarios();
        this.cargarFormasPago();
        this.cargarImpuestos();

        if (!this.editId) {
            this.form.fecha_proxima = this.fechaHoyLocal();
            this.form.dia_ejecucion = Number(this.form.fecha_proxima.slice(-2));
            this.form.hora12 = 8;
            this.form.minuto = 0;
            this.form.ampm = 'AM';
        }

        if (this.editId) {
            this.cargarProgramada(this.editId);
        } else {
            this.cargarDocumentosFacturacion();
        }
    }

    public onFechaProximaChange(): void {
        if (!this.form.fecha_proxima) {
            return;
        }
        const dia = Number(this.form.fecha_proxima.slice(-2));
        if (dia >= 1 && dia <= 31) {
            this.form.dia_ejecucion = dia;
        }
    }

    public get titulo(): string {
        return this.editId ? 'Editar factura programada' : 'Crear factura programada';
    }

    /**
     * Misma lógica de totales que facturación v2 (`sumTotal`):
     * precio en UI = con IVA; total de línea = total_iva; pie = sub_total + impuestos + total.
     */
    public recalcularTotalesCabecera(): void {
        if (!Array.isArray(this.venta.detalles)) {
            this.venta.detalles = [];
        }
        if (!Array.isArray(this.venta.impuestos)) {
            this.venta.impuestos = [];
        }
        if (
            this.venta.cobrar_impuestos &&
            this.venta.impuestos.length === 0 &&
            this.impuestosCatalogo.length > 0
        ) {
            this.venta.impuestos = this.impuestosCatalogo.map((i: any) => ({ ...i, monto: 0 }));
        }

        const empresaIva = Number(this.apiService.auth_user()?.empresa?.iva ?? 0);
        const paisEmpresa = this.apiService.auth_user()?.empresa?.pais;
        this.venta.detalles.forEach((d: any) => {
            if (String(d?.tipo_gravado || '').toLowerCase() === 'exonerada') {
                return;
            }
            calcularMontosLineaDetalle(d, !!this.venta.cobrar_impuestos, empresaIva, {
                preservePrecioIva: true,
                paisEmpresa,
            });
        });

        this.venta.sub_total = Number(sumarSubTotalEncabezadoVenta(this.venta.detalles)).toFixed(4);
        this.venta.exenta = redondearMoneda(
            this.venta.detalles.reduce((s: number, d: any) => s + (Number(d.exenta) || 0), 0),
        ).toFixed(4);
        this.venta.no_sujeta = redondearMoneda(
            this.venta.detalles.reduce((s: number, d: any) => s + (Number(d.no_sujeta) || 0), 0),
        ).toFixed(4);
        this.venta.gravada = redondearMoneda(
            this.venta.detalles.reduce((s: number, d: any) => s + (Number(d.gravada) || 0), 0),
        ).toFixed(4);
        this.venta.descuento = redondearMoneda(
            this.venta.detalles.reduce((s: number, d: any) => s + (Number(d.descuento) || 0), 0),
        ).toFixed(4);

        const ivaEncabezado = acumularImpuestosVentaConCierreResidual(
            this.venta.impuestos,
            this.venta.detalles,
            !!this.venta.cobrar_impuestos,
            empresaIva,
            paisEmpresa,
            0,
        );
        this.venta.iva = ivaEncabezado.toFixed(4);

        this.venta.total = sumarTotalEncabezadoVenta(this.venta.detalles, this.venta.impuestos, {
            empresaIva,
            cobrarImpuestos: !!this.venta.cobrar_impuestos,
            cuentaTerceros: 0,
            ivaPercibido: 0,
            ivaRetenido: 0,
            rentaRetenida: 0,
            descuentoPuntos: 0,
        }).toFixed(2);
    }

    public onDocumentoChange(): void {
        const doc = this.documentos.find((d: any) => Number(d.id) === Number(this.form.id_documento));
        this.venta.nombre_documento = doc?.nombre ?? '';
        // Igual que facturación v2 (`setDocumento`): comercial sin IVA; resto con impuestos.
        this.venta.cobrar_impuestos = this.venta.nombre_documento !== 'Factura comercial';
        if (this.venta.cobrar_impuestos && this.impuestosCatalogo.length) {
            this.venta.impuestos = this.impuestosCatalogo.map((i: any) => ({ ...i, monto: 0 }));
        } else if (!this.venta.cobrar_impuestos) {
            this.venta.impuestos = [];
        }
        sincronizarTipoGravadoPorCobroIva(this.venta.detalles, !!this.venta.cobrar_impuestos);
        this.recalcularTotalesCabecera();
    }

    public get usuariosFiltrados(): any[] {
        const idSucursal = this.venta?.id_sucursal;
        if (idSucursal == null || idSucursal === '') {
            return this.usuarios;
        }
        return this.usuarios.filter(
            (u: any) => u.id_sucursal == null || Number(u.id_sucursal) === Number(idSucursal),
        );
    }

    public setCliente(cliente: any): void {
        this.cliente = cliente ?? { nombre: '' };
        this.venta.cliente = this.cliente;
        this.venta.id_cliente = cliente?.id ?? null;
    }

    public setBodega(): void {
        const bodega = this.bodegas.find((b: any) => Number(b.id) === Number(this.venta.id_bodega));
        if (bodega?.id_sucursal != null && bodega.id_sucursal !== '') {
            this.venta.id_sucursal = bodega.id_sucursal;
        }
        this.cargarDocumentosFacturacion();
    }

    public onDetallesUpdate(venta: any): void {
        const impuestosPrevios = this.venta.impuestos;
        const cobrar = this.venta.cobrar_impuestos;
        this.venta = {
            ...this.venta,
            ...venta,
            detalles: venta?.detalles ?? this.venta.detalles,
            impuestos: impuestosPrevios,
            cobrar_impuestos: cobrar,
        };
        this.recalcularTotalesCabecera();
    }

    public onSumTotal(): void {
        this.recalcularTotalesCabecera();
    }

    private cargarImpuestos(): void {
        this.apiService
            .getAll('impuestos')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (impuestos) => {
                    this.impuestosCatalogo = (impuestos || []).filter(
                        (i: any) => i.aplica_ventas !== false && i.aplica_ventas !== 0,
                    );
                    if (this.venta.cobrar_impuestos && this.venta.impuestos.length === 0) {
                        this.venta.impuestos = this.impuestosCatalogo.map((i: any) => ({
                            ...i,
                            monto: 0,
                        }));
                    }
                    this.recalcularTotalesCabecera();
                },
                error: () => {
                    this.impuestosCatalogo = [];
                },
            });
    }

    public onEmitirAutomaticoChange(): void {
        if (!this.form.emitir_automatico) {
            this.form.enviar_email = false;
        }
    }

    public guardar(): void {
        const payload = this.buildPayload();
        if (!payload) {
            return;
        }

        this.saving = true;
        const req = this.editId
            ? this.apiService.update('ventas-programadas', this.editId, payload)
            : this.apiService.store('ventas-programadas', payload);

        req.pipe(this.untilDestroyed()).subscribe({
            next: (saved) => {
                this.saving = false;
                this.alertService.success(
                    this.editId ? 'Actualizada' : 'Creada',
                    'Factura programada guardada.',
                );
                this.router.navigate(['/ventas/programadas/ver', saved.id]);
            },
            error: (err) => {
                this.saving = false;
                this.alertService.error(err);
            },
        });
    }

    private cargarBodegas(): void {
        this.apiService
            .getAll('bodegas/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (bodegas) => {
                    let list = bodegas ?? [];
                    const user = this.apiService.auth_user();
                    if (this.limitaSucursalPropia()) {
                        list = list.filter(
                            (item: any) => Number(item.id_sucursal) === Number(user?.id_sucursal),
                        );
                    }
                    this.bodegas = list;
                    if (
                        this.venta.id_bodega &&
                        !this.bodegas.some((b: any) => Number(b.id) === Number(this.venta.id_bodega))
                    ) {
                        this.venta.id_bodega = this.bodegas[0]?.id ?? null;
                    } else if (!this.venta.id_bodega && this.bodegas.length) {
                        this.venta.id_bodega = this.bodegas[0].id;
                    }
                    this.sincronizarSucursalDesdeBodega();
                    if (!this.editId) {
                        this.cargarDocumentosFacturacion();
                    }
                },
                error: (err) => this.alertService.error(err),
            });
    }

    private cargarFormasPago(): void {
        this.apiService
            .getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (formas) => {
                    // Multiple requiere montos por método; no aplica en plantilla programada.
                    this.formaPagos = (formas || []).filter(
                        (f: any) => String(f?.nombre || '') !== 'Multiple',
                    );
                    if (
                        this.form.forma_pago &&
                        !this.formaPagos.some((f: any) => f.nombre === this.form.forma_pago)
                    ) {
                        this.formaPagos = [
                            { nombre: this.form.forma_pago },
                            ...this.formaPagos,
                        ];
                    }
                    if (!this.form.forma_pago && this.formaPagos.length) {
                        const efectivo = this.formaPagos.find((f: any) => f.nombre === 'Efectivo');
                        this.form.forma_pago = efectivo?.nombre ?? this.formaPagos[0].nombre;
                    }
                },
                error: () => {
                    this.formaPagos = [{ nombre: 'Efectivo' }];
                },
            });
    }

    private cargarUsuarios(): void {
        this.apiService
            .getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (usuarios) => {
                    let list = usuarios ?? [];
                    const auth = this.apiService.auth_user();
                    const listaCompleta =
                        auth?.tipo === 'Administrador' ||
                        auth?.tipo === 'Supervisor' ||
                        auth?.tipo === 'Supervisor Limitado' ||
                        ((auth?.tipo === 'Ventas' || auth?.tipo === 'Ventas Limitado') &&
                            this.apiService.isVentasPuedeCambiarVendedorFacturacion?.());
                    if (!listaCompleta) {
                        list = list.filter((item: any) => Number(item.id) === Number(auth?.id));
                    }
                    this.usuarios = list;
                    if (!this.venta.id_usuario) {
                        this.venta.id_usuario = auth?.id ?? null;
                    }
                },
                error: () => {
                    this.usuarios = [];
                },
            });
    }

    private limitaSucursalPropia(): boolean {
        const tipo = this.apiService.auth_user()?.tipo;
        return tipo === 'Ventas Limitado' || tipo === 'Supervisor Limitado';
    }

    private sincronizarSucursalDesdeBodega(): void {
        if (!this.bodegas?.length) {
            return;
        }
        let bodega = this.bodegas.find((b: any) => Number(b.id) === Number(this.venta?.id_bodega));
        if (!bodega && this.venta?.id_sucursal != null && this.venta.id_sucursal !== '') {
            bodega = this.bodegas.find(
                (b: any) => Number(b.id_sucursal) === Number(this.venta.id_sucursal),
            );
        }
        if (!bodega) {
            bodega = this.bodegas[0];
        }
        if (bodega?.id_sucursal != null && bodega.id_sucursal !== '') {
            this.venta.id_sucursal = bodega.id_sucursal;
            this.venta.id_bodega = Number(bodega.id);
        }
    }

    /** Misma fuente y filtro que facturación v2 (sucursal + whitelist por país). */
    private cargarDocumentosFacturacion(): void {
        this.apiService
            .getAll('documentos/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (documentos) => {
                    const idSucursal = this.venta.id_sucursal;
                    const deSucursal = (documentos || []).filter(
                        (doc: any) =>
                            idSucursal != null && Number(doc.id_sucursal) === Number(idSucursal),
                    );
                    const nombresVenta = nombresDocumentosVentaNormales(
                        this.apiService.auth_user()?.empresa,
                    );
                    const porWhitelist = deSucursal.filter((doc: any) =>
                        nombresVenta.includes(String(doc.nombre || '').trim()),
                    );
                    this.documentos = porWhitelist.length
                        ? porWhitelist
                        : deSucursal.filter(
                              (doc: any) =>
                                  doc.nombre !== 'Cotización' && doc.nombre !== 'Orden de compra',
                          );

                    if (
                        this.form.id_documento &&
                        !this.documentos.some((d: any) => Number(d.id) === Number(this.form.id_documento))
                    ) {
                        const actual = (documentos || []).find(
                            (d: any) => Number(d.id) === Number(this.form.id_documento),
                        );
                        if (actual) {
                            this.documentos = [actual, ...this.documentos];
                        } else if (!this.editId) {
                            this.form.id_documento = null;
                        }
                    }

                    if (!this.editId && !this.form.id_documento) {
                        const pred = this.documentos.find((x: any) => Number(x.predeterminado) === 1);
                        this.form.id_documento = pred?.id ?? this.documentos[0]?.id ?? null;
                    }
                    this.onDocumentoChange();
                },
                error: (err) => this.alertService.error(err),
            });
    }

    private cargarProgramada(id: number): void {
        this.loading = true;
        this.apiService
            .read('ventas-programadas/', id)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (p) => {
                    this.form.id_documento = p.id_documento ?? null;
                    this.form.frecuencia = p.frecuencia === 'anual' ? 'anual' : 'mensual';
                    this.form.dia_ejecucion = Number(p.dia_ejecucion) || 1;
                    this.aplicarProximaDesdeApi(p.proxima_ejecucion, String(p.hora_ejecucion ?? ''));
                    this.form.activo = !!p.activo;
                    this.form.emitir_automatico = !!p.emitir_automatico;
                    this.form.enviar_email = !!p.enviar_email;
                    this.form.max_ejecuciones = p.max_ejecuciones ?? null;
                    this.form.forma_pago = p.forma_pago || 'Efectivo';
                    this.form.condicion = p.condicion === 'Crédito' ? 'Crédito' : 'Contado';

                    if (p.id_sucursal) {
                        this.venta.id_sucursal = p.id_sucursal;
                    }
                    if (p.id_bodega) {
                        this.venta.id_bodega = p.id_bodega;
                    }
                    this.venta.id_usuario = p.id_vendedor ?? this.venta.id_usuario;
                    this.venta.observaciones = p.observaciones ?? '';

                    this.cliente = p.cliente ?? { id: p.id_cliente, nombre: '' };
                    this.venta.id_cliente = p.id_cliente ?? null;
                    this.venta.cliente = this.cliente;

                    this.venta.detalles = (p.detalles ?? []).map((d: any) => {
                        const precio = Number(d.precio) || 0;
                        const precioConIva =
                            Number(d.precio_con_iva) ||
                            Number(d.precio_iva) ||
                            precio;
                        const total = Number(d.total) || 0;
                        const iva = Number(d.iva) || 0;
                        return {
                            ...d,
                            id_producto: d.id_producto,
                            descripcion: d.descripcion || d.nombre_producto || 'Producto',
                            cantidad: Number(d.cantidad) || 1,
                            precio,
                            precio_iva: redondearMoneda(precioConIva).toFixed(2),
                            precio_sin_iva: Number(d.precio_sin_iva) || precio,
                            precio_con_iva: precioConIva,
                            total,
                            total_iva: Number(d.total_iva) || redondearMoneda(total + iva),
                            descuento: Number(d.descuento) || 0,
                            tipo_gravado: d.tipo_gravado || 'gravada',
                            porcentaje_impuesto:
                                d.porcentaje_impuesto != null ? Number(d.porcentaje_impuesto) : null,
                            costo: Number(d.costo) || 0,
                            id_presentacion: d.id_presentacion ?? null,
                            gravada: Number(d.gravada) || 0,
                            exenta: Number(d.exenta) || 0,
                            no_sujeta: Number(d.no_sujeta) || 0,
                            iva,
                            sub_total: Number(d.sub_total) || 0,
                        };
                    });

                    this.loading = false;
                    this.cargarDocumentosFacturacion();
                },
                error: (err) => {
                    this.loading = false;
                    this.alertService.error(err);
                },
            });
    }

    private buildPayload(): Record<string, unknown> | null {
        if (!this.venta.id_cliente || !this.form.id_documento) {
            this.alertService.error('Seleccione cliente y tipo de documento.');
            return null;
        }
        if (!this.venta.id_bodega) {
            this.alertService.error('Seleccione una bodega.');
            return null;
        }
        if (!this.form.fecha_proxima) {
            this.alertService.error('Seleccione la fecha de próxima ejecución.');
            return null;
        }
        this.onFechaProximaChange();

        const detalles = (this.venta.detalles ?? [])
            .filter((d: any) => d.id_producto)
            .map((d: any) => {
                const n = (v: unknown) => Number(v) || 0;
                return {
                    id_producto: d.id_producto,
                    id_presentacion: d.id_presentacion ?? null,
                    descripcion: d.descripcion ?? null,
                    cantidad: n(d.cantidad),
                    precio: n(d.precio),
                    precio_sin_iva: n(d.precio_sin_iva ?? d.precio),
                    precio_con_iva: n(d.precio_con_iva),
                    costo: n(d.costo),
                    total_costo: redondearMoneda(n(d.costo) * n(d.cantidad)),
                    descuento: n(d.descuento),
                    sub_total: n(d.sub_total),
                    total: n(d.total),
                    tipo_gravado: d.tipo_gravado || 'gravada',
                    gravada: n(d.gravada),
                    exenta: n(d.exenta),
                    no_sujeta: n(d.no_sujeta),
                    iva: redondearMoneda(n(d.iva)),
                    porcentaje_impuesto: d.porcentaje_impuesto ?? null,
                    id_vendedor: d.id_vendedor ?? this.venta.id_usuario ?? null,
                };
            });

        if (detalles.length < 1) {
            this.alertService.error('Agregue al menos un producto o servicio.');
            return null;
        }

        this.recalcularTotalesCabecera();
        const emitir = !!this.form.emitir_automatico;

        return {
            id_cliente: this.venta.id_cliente,
            id_documento: this.form.id_documento,
            id_sucursal: this.venta.id_sucursal ?? null,
            id_bodega: this.venta.id_bodega ?? null,
            id_vendedor: this.venta.id_usuario ?? null,
            observaciones: this.venta.observaciones || null,
            forma_pago: this.form.forma_pago || 'Efectivo',
            condicion: this.form.condicion || 'Contado',
            frecuencia: this.form.frecuencia,
            dia_ejecucion: Number(this.form.dia_ejecucion),
            hora_ejecucion: this.hora24DesdeForm(),
            proxima_ejecucion: this.form.fecha_proxima || null,
            activo: !!this.form.activo,
            emitir_automatico: emitir,
            enviar_email: emitir ? !!this.form.enviar_email : false,
            max_ejecuciones: this.form.max_ejecuciones ? Number(this.form.max_ejecuciones) : null,
            detalles,
            sub_total: Number(this.venta.sub_total) || 0,
            descuento: Number(this.venta.descuento) || 0,
            gravada: Number(this.venta.gravada) || 0,
            exenta: Number(this.venta.exenta) || 0,
            no_sujeta: Number(this.venta.no_sujeta) || 0,
            iva: Number(this.venta.iva) || 0,
            total: Number(this.venta.total) || 0,
        };
    }

    private fechaHoyLocal(): string {
        const d = new Date();
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    private hora24DesdeForm(): string {
        let h = Number(this.form.hora12) || 12;
        if (this.form.ampm === 'AM') {
            h = h === 12 ? 0 : h;
        } else {
            h = h === 12 ? 12 : h + 12;
        }
        const min = Number(this.form.minuto) || 0;
        return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
    }

    private aplicarProximaDesdeApi(proxima: unknown, horaFallback: string): void {
        const raw = String(proxima ?? '');
        const fechaMatch = raw.match(/^(\d{4}-\d{2}-\d{2})/);
        this.form.fecha_proxima = fechaMatch?.[1] ?? this.fechaHoyLocal();
        this.onFechaProximaChange();

        let hh = 8;
        let mm = 0;
        const horaSrc = raw.includes('T') || raw.includes(' ')
            ? raw.replace('T', ' ').split(' ')[1] ?? horaFallback
            : horaFallback;
        const parts = String(horaSrc || '08:00').split(':');
        hh = Number(parts[0]) || 0;
        mm = Number(parts[1]) || 0;
        this.form.minuto = Math.min(59, Math.max(0, mm));
        if (hh === 0) {
            this.form.hora12 = 12;
            this.form.ampm = 'AM';
        } else if (hh === 12) {
            this.form.hora12 = 12;
            this.form.ampm = 'PM';
        } else if (hh > 12) {
            this.form.hora12 = hh - 12;
            this.form.ampm = 'PM';
        } else {
            this.form.hora12 = hh;
            this.form.ampm = 'AM';
        }
    }
}
