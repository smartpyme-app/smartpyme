import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';
import { CurrencyPipe } from '@pipes/currency-format.pipe';

import {
    calcularMontosLineaDetalle,
    normalizarPorcentajeImpuestoDetalle,
    redondearMoneda,
} from '@utils/impuestos-venta.util';

// Los montos fiscales (gravada, exenta, iva, sub_total, precio_*) los escribe calcularMontosLineaDetalle.
type LineaDetalle = {
    id_producto: number | null;
    cantidad: number;
    precio: number;
    total: number;
    tipo_gravado: string;
    porcentaje_impuesto?: number | null;
    costo?: number;
    [campo: string]: any;
};

@Component({
    selector: 'app-programada-form',
    templateUrl: './programada-form.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, CurrencyPipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProgramadaFormComponent extends BaseComponent implements OnInit {
    public clientes: any[] = [];
    public documentos: any[] = [];
    public productos: any[] = [];
    public saving = false;
    public loading = false;
    public editId: number | null = null;

    public form = {
        nombre: '',
        id_cliente: null as number | null,
        id_documento: null as number | null,
        frecuencia: 'mensual' as 'mensual' | 'anual',
        dia_ejecucion: 1,
        hora_ejecucion: '08:00',
        activo: true,
        emitir_automatico: false,
        enviar_email: false,
        max_ejecuciones: null as number | null,
        detalles: [] as LineaDetalle[],
    };

    constructor(
        private apiService: ApiService,
        private alertService: AlertService,
        private route: ActivatedRoute,
        private router: Router,
        private cdr: ChangeDetectorRef,
    ) {
        super();
    }

    ngOnInit(): void {
        const idParam = this.route.snapshot.paramMap.get('id');
        this.editId = idParam ? Number(idParam) : null;
        this.form.detalles = [this.nuevaLinea()];

        this.apiService
            .getAll('clientes/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (clientes) => {
                    this.clientes = clientes ?? [];
                    this.cdr.markForCheck();
                },
                error: (err) => this.alertService.error(err),
            });

        this.apiService
            .getAll('documentos')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (documentos) => {
                    this.documentos = documentos ?? [];
                    this.cdr.markForCheck();
                },
                error: (err) => this.alertService.error(err),
            });

        this.apiService
            .getAll('productos/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (productos) => {
                    this.productos = productos ?? [];
                    this.cdr.markForCheck();
                },
                error: (err) => this.alertService.error(err),
            });

        if (this.editId) {
            this.cargarProgramada(this.editId);
        }
    }

    public get titulo(): string {
        return this.editId ? 'Editar factura programada' : 'Crear factura programada';
    }

    public get totalDocumento(): number {
        // Total con IVA, igual que el total de una venta.
        return this.form.detalles.reduce(
            (s, d) => s + (Number(d['total_iva']) || Number(d.total) || 0),
            0,
        );
    }

    public onEmitirAutomaticoChange(): void {
        if (!this.form.emitir_automatico) {
            this.form.enviar_email = false;
        }
    }

    public agregarLinea(): void {
        this.form.detalles.push(this.nuevaLinea());
    }

    public quitarLinea(index: number): void {
        if (this.form.detalles.length <= 1) {
            return;
        }
        this.form.detalles.splice(index, 1);
    }

    public actualizarLinea(linea: LineaDetalle): void {
        // Misma lógica fiscal que facturación (precio = sin IVA; total = neto de la línea).
        const empresa = this.apiService.auth_user()?.empresa;
        calcularMontosLineaDetalle(linea, true, empresa?.iva, { paisEmpresa: empresa?.pais });
        linea.total = Number(linea.total) || 0;
    }

    public onProductoChange(linea: LineaDetalle): void {
        const prod = this.productos.find((p) => p.id === linea.id_producto);
        if (prod) {
            const iva = this.apiService.auth_user()?.empresa?.iva;
            linea.porcentaje_impuesto = normalizarPorcentajeImpuestoDetalle(prod.porcentaje_impuesto, iva);
            linea.tipo_gravado = 'gravada';
            linea.costo = Number(prod.costo) || 0;
            if (prod.precio != null && Number(linea.precio) === 0) {
                linea.precio = Number(prod.precio) || 0;
            }
        }
        this.actualizarLinea(linea);
        this.cdr.markForCheck();
    }

    public guardar(): void {
        const payload = this.buildPayload();
        if (!payload) {
            return;
        }

        this.saving = true;
        this.cdr.markForCheck();
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
                this.cdr.markForCheck();
            },
        });
    }

    private cargarProgramada(id: number): void {
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService
            .read('ventas-programadas/', id)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (p) => {
                    this.form.nombre = p.nombre ?? '';
                    this.form.id_cliente = p.id_cliente ?? null;
                    this.form.id_documento = p.id_documento ?? null;
                    this.form.frecuencia = p.frecuencia === 'anual' ? 'anual' : 'mensual';
                    this.form.dia_ejecucion = Number(p.dia_ejecucion) || 1;
                    this.form.hora_ejecucion = this.horaParaInput(String(p.hora_ejecucion ?? ''));
                    this.form.activo = !!p.activo;
                    this.form.emitir_automatico = !!p.emitir_automatico;
                    this.form.enviar_email = !!p.enviar_email;
                    this.form.max_ejecuciones = p.max_ejecuciones ?? null;
                    this.form.detalles = (p.detalles ?? []).map((d: any) => ({
                        id_producto: d.id_producto,
                        cantidad: Number(d.cantidad) || 1,
                        precio: Number(d.precio) || 0,
                        total: Number(d.total) || 0,
                        tipo_gravado: d.tipo_gravado || 'gravada',
                        porcentaje_impuesto: d.porcentaje_impuesto != null ? Number(d.porcentaje_impuesto) : null,
                        costo: Number(d.costo) || 0,
                        id_presentacion: d.id_presentacion ?? null,
                        descripcion: d.descripcion ?? null,
                    }));
                    this.form.detalles.forEach((l) => this.actualizarLinea(l));
                    if (this.form.detalles.length < 1) {
                        this.form.detalles = [this.nuevaLinea()];
                    }
                    this.loading = false;
                    this.cdr.markForCheck();
                },
                error: (err) => {
                    this.loading = false;
                    this.alertService.error(err);
                    this.cdr.markForCheck();
                },
            });
    }

    private buildPayload(): Record<string, unknown> | null {
        if (!this.form.nombre?.trim()) {
            this.alertService.error('Indique un nombre para la plantilla.');
            return null;
        }
        if (!this.form.id_cliente || !this.form.id_documento) {
            this.alertService.error('Seleccione cliente y tipo de documento.');
            return null;
        }

        const detalles = this.form.detalles
            .filter((d) => d.id_producto)
            .map((d) => {
                this.actualizarLinea(d);
                const n = (v: unknown) => Number(v) || 0;
                return {
                    id_producto: d.id_producto,
                    id_presentacion: d['id_presentacion'] ?? null,
                    descripcion: d['descripcion'] ?? null,
                    cantidad: n(d.cantidad),
                    precio: n(d.precio),
                    precio_sin_iva: n(d['precio_sin_iva']),
                    precio_con_iva: n(d['precio_con_iva']),
                    costo: n(d.costo),
                    total_costo: redondearMoneda(n(d.costo) * n(d.cantidad)),
                    descuento: n(d['descuento']),
                    sub_total: n(d['sub_total']),
                    total: n(d.total),
                    tipo_gravado: d.tipo_gravado || 'gravada',
                    gravada: n(d['gravada']),
                    exenta: n(d['exenta']),
                    no_sujeta: n(d['no_sujeta']),
                    iva: redondearMoneda(n(d['iva'])),
                    porcentaje_impuesto: d.porcentaje_impuesto ?? null,
                };
            });

        if (detalles.length < 1) {
            this.alertService.error('Agregue al menos una línea con producto.');
            return null;
        }

        const suma = (k: 'sub_total' | 'gravada' | 'exenta' | 'no_sujeta' | 'iva' | 'total' | 'descuento') =>
            redondearMoneda(detalles.reduce((s, d) => s + d[k], 0));
        const user = this.apiService.auth_user();
        const emitir = !!this.form.emitir_automatico;

        return {
            nombre: this.form.nombre.trim(),
            id_cliente: this.form.id_cliente,
            id_documento: this.form.id_documento,
            frecuencia: this.form.frecuencia,
            dia_ejecucion: Number(this.form.dia_ejecucion),
            hora_ejecucion: this.form.hora_ejecucion,
            activo: !!this.form.activo,
            emitir_automatico: emitir,
            enviar_email: emitir ? !!this.form.enviar_email : false,
            max_ejecuciones: this.form.max_ejecuciones ? Number(this.form.max_ejecuciones) : null,
            detalles,
            // Solo al crear: en edición el backend conserva sucursal/bodega/vendedor originales.
            ...(this.editId
                ? {}
                : {
                      id_sucursal: user?.id_sucursal ?? null,
                      id_bodega: user?.id_bodega ?? null,
                      id_vendedor: user?.id ?? null,
                  }),
            sub_total: suma('sub_total'),
            descuento: suma('descuento'),
            gravada: suma('gravada'),
            exenta: suma('exenta'),
            no_sujeta: suma('no_sujeta'),
            iva: suma('iva'),
            total: redondearMoneda(suma('total') + suma('iva')),
        };
    }

    private nuevaLinea(): LineaDetalle {
        return { id_producto: null, cantidad: 1, precio: 0, total: 0, tipo_gravado: 'gravada' };
    }

    private horaParaInput(hora: string): string {
        if (!hora) {
            return '08:00';
        }
        const parts = hora.split(':');
        return `${String(parts[0] ?? '08').padStart(2, '0')}:${String(parts[1] ?? '00').padStart(2, '0')}`;
    }
}