import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { BaseComponent } from '@shared/base/base.component';
import { descargarBlob, manejarErrorDescargaLibroIva } from '@views/contabilidad/libro-iva-shared/libro-iva-descarga.util';

type TipoReporte = 'libro-activos' | 'depreciacion-periodo' | 'estado-activos' | 'bajas-periodo';

@Component({
    selector: 'app-activos-reportes',
    templateUrl: './reportes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TooltipModule, CurrencyPipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ActivosReportesComponent extends BaseComponent implements OnInit {

    readonly tipos: { id: TipoReporte; label: string }[] = [
        { id: 'libro-activos', label: 'Libro de activos' },
        { id: 'depreciacion-periodo', label: 'Depreciación período' },
        { id: 'estado-activos', label: 'Estado de activos' },
        { id: 'bajas-periodo', label: 'Bajas del período' },
    ];

    readonly columnasVacias = ['—'];

    private readonly etiquetasTotales: Record<string, string> = {
        valor_compra: 'Valor compra',
        valor_en_libros: 'Valor en libros',
        monto: 'Monto depreciado',
        depreciacion_acumulada: 'Dep. acumulada',
        monto_venta: 'Monto venta',
    };

    public tipo: TipoReporte = 'libro-activos';
    public filtros: any = {};
    public reporte: any = null;
    public loading = false;
    public downloading = false;
    public categorias: any[] = [];
    public sucursales: any[] = [];

    constructor(
        public apiService: ApiService,
        protected alertService: AlertService,
        private cdr: ChangeDetectorRef,
    ) {
        super();
    }

    ngOnInit() {
        this.resetFiltros();

        this.apiService.getAll('activos/categorias', { list: 1 })
            .pipe(this.untilDestroyed())
            .subscribe(categorias => {
                this.categorias = categorias;
                this.cdr.markForCheck();
            }, error => this.alertService.error(error));

        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
                this.sucursales = sucursales;
                this.cdr.markForCheck();
            }, error => this.alertService.error(error));

        this.generar();
    }

    public cambiarTipo(tipo: TipoReporte) {
        this.tipo = tipo;
        this.resetFiltros();
        this.generar();
    }

    private resetFiltros() {
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const firstDay = `${y}-${m}-01`;
        const lastDay = new Date(y, now.getMonth() + 1, 0).toISOString().slice(0, 10);

        this.filtros = {
            inicio: firstDay,
            fin: lastDay,
            fecha_corte: this.apiService.date(),
            periodo: `${y}-${m}`,
            id_sucursal: '',
            id_categoria: '',
        };
    }

    public generar() {
        this.loading = true;
        this.apiService.getAll(`activos/reportes/${this.tipo}`, this.paramsReporte())
            .pipe(this.untilDestroyed())
            .subscribe(reporte => {
                this.reporte = reporte;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

    public descargarExcel() {
        this.downloading = true;
        this.apiService.export(`activos/reportes/${this.tipo}/descargar/xlsx`, this.paramsReporte())
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (data: Blob) => {
                    const nombre = (this.reporte?.titulo ?? 'Reporte-activos').replace(/\s+/g, '-');
                    descargarBlob(data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', `${nombre}.xlsx`);
                    this.downloading = false;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    manejarErrorDescargaLibroIva(error, this.alertService);
                    this.downloading = false;
                    this.cdr.markForCheck();
                },
            });
    }

    public descargarPdf() {
        const q = new URLSearchParams(this.paramsReporte() as Record<string, string>);
        q.set('token', this.apiService.auth_token());
        q.set('_ts', String(Date.now()));
        window.open(`${this.apiService.baseUrl}/api/activos/reportes/${this.tipo}/descargar/pdf?${q.toString()}`, '_blank');
    }

    totalesEntries(): { key: string; label: string; value: number; esMoneda: boolean }[] {
        if (!this.reporte?.totales) {
            return [];
        }
        return Object.entries(this.reporte.totales).map(([key, value]) => ({
            key,
            label: this.etiquetasTotales[key] ?? key.replace(/_/g, ' '),
            value: Number(value),
            esMoneda: this.esClaveMoneda(key),
        }));
    }

    esColumnaNumerica(col: string): boolean {
        const numericas = ['valor compra', 'valor en libros', 'monto', 'acumulada', 'dep. acumulada', 'monto venta', 'cantidad'];
        return numericas.includes(col?.toLowerCase() ?? '');
    }

    esColumnaEstado(col: string): boolean {
        return col?.toLowerCase() === 'estado';
    }

    clasesBadgeEstado(valor: unknown): Record<string, boolean> {
        const v = String(valor ?? '').toLowerCase();

        if (['pendiente', 'aplicada', 'cancelada'].includes(v)) {
            return {
                'bg-warning': v === 'pendiente',
                'bg-success': v === 'aplicada',
                'bg-danger': v === 'cancelada',
                'bg-secondary': !['pendiente', 'aplicada', 'cancelada'].includes(v),
            };
        }

        return {
            'bg-success': v === 'en uso',
            'bg-warning': v === 'en reparación' || v === 'en reparacion',
            'bg-danger': v === 'desechado' || v === 'baja',
            'bg-secondary': !['en uso', 'en reparación', 'en reparacion', 'desechado', 'baja'].includes(v),
        };
    }

    private esClaveMoneda(key: string): boolean {
        return key.includes('valor') || key.includes('monto') || key.includes('acumulada');
    }

    private paramsReporte(): Record<string, string | number> {
        const params: Record<string, string | number> = {};

        if (this.tipo === 'libro-activos' || this.tipo === 'bajas-periodo') {
            params['inicio'] = this.filtros.inicio;
            params['fin'] = this.filtros.fin;
        }
        if (this.tipo === 'depreciacion-periodo') {
            params['periodo'] = this.filtros.periodo;
        }
        if (this.tipo === 'estado-activos') {
            params['fecha_corte'] = this.filtros.fecha_corte;
        }
        if (this.filtros.id_sucursal) {
            params['id_sucursal'] = this.filtros.id_sucursal;
        }
        if (this.filtros.id_categoria && this.tipo !== 'depreciacion-periodo' && this.tipo !== 'bajas-periodo') {
            params['id_categoria'] = this.filtros.id_categoria;
        }

        return params;
    }
}
