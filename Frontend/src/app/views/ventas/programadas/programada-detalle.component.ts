import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';

@Component({
    selector: 'app-programada-detalle',
    templateUrl: './programada-detalle.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, CurrencyPipe, PaginationComponent, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProgramadaDetalleComponent extends BaseComponent implements OnInit {
    public programada: any = null;
    public ejecuciones: any = {};
    public loading = true;
    public loadingEjecuciones = false;
    public reintentandoId: number | null = null;
    public ejecucionDetalle: any = null;
    public modalRef?: BsModalRef;
    private id = 0;

    constructor(
        private apiService: ApiService,
        private alertService: AlertService,
        private route: ActivatedRoute,
        private cdr: ChangeDetectorRef,
        private modalService: BsModalService,
    ) {
        super();
    }

    ngOnInit(): void {
        this.id = Number(this.route.snapshot.paramMap.get('id'));
        this.cargarProgramada();
        this.cargarEjecuciones(1);
    }

    public setPagination(event: { page?: number }): void {
        this.cargarEjecuciones(event?.page ?? 1);
    }

    public abrirDetalleEjecucion(template: TemplateRef<any>, ejecucion: any): void {
        this.ejecucionDetalle = ejecucion;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: true });
        this.cdr.markForCheck();
    }

    public puedeReintentar(ejecucion: any): boolean {
        if (ejecucion?.estado !== 'error') {
            return false;
        }
        const periodo = ejecucion?.periodo;
        if (!periodo) {
            return true;
        }
        return !(this.ejecuciones?.data || []).some(
            (x: any) => x?.periodo === periodo && x?.estado === 'ok',
        );
    }

    public reintentar(ejecucion: any): void {
        if (!ejecucion?.id || this.reintentandoId || !this.puedeReintentar(ejecucion)) {
            return;
        }
        if (!confirm('¿Reintentar ahora esta ejecución fallida?')) {
            return;
        }
        this.reintentandoId = Number(ejecucion.id);
        this.cdr.markForCheck();
        this.apiService
            .store(`ventas-programadas/${this.id}/reintentar`, { id_ejecucion: ejecucion.id })
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (res) => {
                    this.reintentandoId = null;
                    if (res?.programada) {
                        this.programada = res.programada;
                    }
                    const estado = res?.ejecucion?.estado;
                    if (estado === 'ok') {
                        this.alertService.success('Reintento', 'Ejecución completada correctamente.');
                    } else if (estado === 'omitida') {
                        this.alertService.warning('Reintento', res?.ejecucion?.mensaje_error || 'Ejecución omitida.');
                    } else {
                        this.alertService.error(
                            res?.ejecucion?.mensaje_error || 'El reintento volvió a fallar.',
                        );
                    }
                    this.cargarEjecuciones(this.ejecuciones?.current_page || 1);
                    this.cdr.markForCheck();
                },
                error: (err) => {
                    this.reintentandoId = null;
                    this.alertService.error(err);
                    this.cdr.markForCheck();
                },
            });
    }

    public nombreCliente(): string {
        const c = this.programada?.cliente;
        if (!c) {
            return '—';
        }
        if (c.tipo === 'Empresa') {
            return c.nombre_empresa || c.nombre || '—';
        }
        return c.nombre_completo || [c.nombre, c.apellido].filter(Boolean).join(' ') || '—';
    }

    public etiquetaFrecuencia(frecuencia: string): string {
        const map: Record<string, string> = { mensual: 'Mensual', anual: 'Anual' };
        return map[frecuencia] ?? frecuencia ?? '—';
    }

    public etiquetaVentaEjecucion(e: any): string {
        const v = e?.venta;
        if (!v) {
            return e?.id_venta ? `#${e.id_venta}` : '—';
        }
        const doc = v.documento?.nombre || v.nombre_documento || 'Documento';
        const corr = v.correlativo != null && v.correlativo !== '' ? v.correlativo : e.id_venta;
        return `${doc} #${corr}`;
    }

    /** Hora API (HH:mm[:ss]) → 12 h con AM/PM. */
    public formatoHora12(hora: unknown): string {
        const parts = String(hora ?? '').split(':');
        if (parts.length < 2) {
            return '—';
        }
        let hh = Number(parts[0]);
        const mm = String(Number(parts[1]) || 0).padStart(2, '0');
        if (!Number.isFinite(hh)) {
            return '—';
        }
        const ampm = hh >= 12 ? 'PM' : 'AM';
        hh = hh % 12;
        if (hh === 0) {
            hh = 12;
        }
        return `${hh}:${mm} ${ampm}`;
    }

    private cargarProgramada(): void {
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService
            .read('ventas-programadas/', this.id)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (p) => {
                    this.programada = p;
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

    private cargarEjecuciones(page: number): void {
        this.loadingEjecuciones = true;
        this.cdr.markForCheck();
        this.apiService
            .getAll(`ventas-programadas/${this.id}/ejecuciones`, { page, paginate: 15 })
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (ejecuciones) => {
                    this.ejecuciones = ejecuciones;
                    this.loadingEjecuciones = false;
                    this.cdr.markForCheck();
                },
                error: (err) => {
                    this.loadingEjecuciones = false;
                    this.alertService.error(err);
                    this.cdr.markForCheck();
                },
            });
    }
}