import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseFilteredPaginatedComponent } from '@shared/base/base-filtered-paginated.component';
import { TruncatePipe } from '@pipes/truncate.pipe';

@Component({
    selector: 'app-programadas',
    templateUrl: './programadas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent, TruncatePipe, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProgramadasComponent extends BaseFilteredPaginatedComponent implements OnInit {
    public programadas: any = {};
    private readonly filtrosStorageKey = 'ventasProgramadasFiltros';

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        private cdr: ChangeDetectorRef,
    ) {
        super(apiService, alertService);
    }

    ngOnInit(): void {
        this.loadAll();
    }

    public loadAll(): void {
        const saved = localStorage.getItem(this.filtrosStorageKey);
        if (saved) {
            this.filtros = JSON.parse(saved);
        } else {
            this.filtros = { buscador: '', paginate: 10, page: 1 };
        }
        this.aplicarFiltros();
    }

    public buscar(): void {
        this.filtros.page = 1;
        this.aplicarFiltros();
    }

    protected aplicarFiltros(): void {
        localStorage.setItem(this.filtrosStorageKey, JSON.stringify(this.filtros));
        this.loading = true;
        this.cdr.markForCheck();
        const params = {
            q: this.filtros.buscador || '',
            paginate: this.filtros.paginate || 10,
            page: this.filtros.page || 1,
        };
        this.apiService
            .getAll('ventas-programadas', params)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (programadas) => {
                    this.programadas = programadas;
                    this.loading = false;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                },
            });
    }

    public toggleActivo(item: any): void {
        const activo = !item.activo;
        const msg = activo
            ? '¿Activar esta factura programada?'
            : '¿Desactivar esta factura programada?';
        if (!confirm(msg)) {
            return;
        }
        this.apiService
            .patch('ventas-programadas', item.id + '/activo', { activo })
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (updated) => {
                    item.activo = updated.activo;
                    this.alertService.success(
                        'Actualizado',
                        activo ? 'Factura programada activada.' : 'Factura programada desactivada.',
                    );
                    this.cdr.markForCheck();
                },
                error: (error) => this.alertService.error(error),
            });
    }

    public nombreCliente(item: any): string {
        const c = item?.cliente;
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
}