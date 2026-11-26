import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';
import { CurrencyPipe } from '@pipes/currency-format.pipe';

type LineaDetalle = {
    id_producto: number | null;
    cantidad: number;
    precio: number;
    total: number;
    tipo_gravado: string;
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
        return this.form.detalles.reduce((s, d) => s + (Number(d.total) || 0), 0);
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
        const cant = Number(linea.cantidad) || 0;
        const precio = Number(linea.precio) || 0;
        linea.total = Math.round(cant * precio * 100) / 100;
    }

    public onProductoChange(linea: LineaDetalle): void {
        const prod = this.productos.find((p) => p.id === linea.id_producto);
        if (prod?.precio != null && linea.precio === 0) {
            linea.precio = Number(prod.precio) || 0;
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
                    }));
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
                return {
                    id_producto: d.id_producto,
                    cantidad: Number(d.cantidad),
                    precio: Number(d.precio),
                    total: Number(d.total),
                    tipo_gravado: d.tipo_gravado || 'gravada',
                };
            });

        if (detalles.length < 1) {
            this.alertService.error('Agregue al menos una línea con producto.');
            return null;
        }

        const total = detalles.reduce((s, d) => s + d.total, 0);
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
            sub_total: total,
            total,
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