import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';

@Component({
    selector: 'app-programada-detalle',
    templateUrl: './programada-detalle.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, CurrencyPipe, PaginationComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProgramadaDetalleComponent extends BaseComponent implements OnInit {
    public programada: any = null;
    public ejecuciones: any = {};
    public loading = true;
    public loadingEjecuciones = false;
    private id = 0;

    constructor(
        private apiService: ApiService,
        private alertService: AlertService,
        private route: ActivatedRoute,
        private cdr: ChangeDetectorRef,
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