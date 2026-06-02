import { Component, OnInit, ViewChild } from '@angular/core';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { WebdatarocksComponent } from '@webdatarocks/ngx-webdatarocks';

@Component({
    selector: 'app-flujo-efectivo',
    templateUrl: './flujo-efectivo.component.html',
})
export class FlujoEfectivoComponent implements OnInit {

    @ViewChild('pivot') pivotComponent!: WebdatarocksComponent;

    public loading = false;

    /** Filtros de fecha */
    public filtros = {
        inicio: '',
        fin:    '',
    };

    /** Resultado del endpoint */
    public datos: any = null;

    /** Configuración del reporte de WebDataRocks */
    public report: any = {};

    /** Configuración global para WebDataRocks (Localización a Español) */
    public globalReport: any = {
        localization: 'https://cdn.webdatarocks.com/loc/es.json'
    };

    private pivotInstance: any = null;

    constructor(
        public apiService: ApiService,
        private alertService: AlertService,
    ) {}

    ngOnInit(): void {
        // Rango por defecto: a partir de este mes, los próximos 6 meses (inclusive)
        const hoy = new Date();
        this.filtros.inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
            .toISOString().split('T')[0];
        this.filtros.fin = new Date(hoy.getFullYear(), hoy.getMonth() + 6, 0)
            .toISOString().split('T')[0];

        this.cargar();
    }

    public cargar(): void {
        this.loading = true;
        this.datos   = null;

        const params = new URLSearchParams({
            inicio: this.filtros.inicio,
            fin:    this.filtros.fin,
        });

        this.apiService
            .getToUrl(`${this.apiService.apiUrl}reportes/flujo-efectivo?${params}`)
            .subscribe(
                (res: any) => {
                    this.datos   = res;
                    this.loading = false;
                    this.actualizarReportePivot();
                },
                (err: any) => {
                    this.alertService.error(err);
                    this.loading = false;
                },
            );
    }

    public onPivotReady(pivot: any): void {
        this.pivotInstance = pivot;
        if (this.datos && this.datos.pivot_data) {
            this.actualizarReportePivot();
        }
    }

    private actualizarReportePivot(): void {
        const pivotData = this.datos?.pivot_data || [];

        this.report = {
            dataSource: {
                data: pivotData
            },
            slice: {
                rows: [
                    { uniqueName: 'Categoría', caption: 'Categoría' },
                    { uniqueName: 'Tipo Plan', caption: 'Tipo Plan' },
                    { uniqueName: 'Plan', caption: 'Plan' },
                    { uniqueName: 'Empresa', caption: 'Empresa' }
                ],
                columns: [
                    { uniqueName: 'Quincena', caption: 'Quincena', sort: 'asc' }
                ],
                measures: [
                    { uniqueName: 'Monto', aggregation: 'sum', format: 'currency', caption: 'Monto previsto' }
                ]
            },
            formats: [
                {
                    name: 'currency',
                    currencySymbol: '$',
                    currencySymbolAlign: 'left',
                    decimalPlaces: 2,
                    thousandsSeparator: ','
                }
            ],
            options: {
                grid: {
                    showGrandTotals: 'on',
                    showTotals: 'on'
                }
            }
        };

        if (this.pivotInstance) {
            this.pivotInstance.setReport(this.report);
        }
    }

    public onBeforeToolbarCreated(toolbar: any): void {
        const tabs = toolbar.getTabs();
        toolbar.getTabs = () => {
            return tabs.filter((tab: any) => 
                tab.id !== 'wdr-tab-connect' &&
                tab.id !== 'wdr-tab-open' &&
                tab.id !== 'wdr-tab-save' &&
                tab.id !== 'wdr-tab-fullscreen'
            );
        };
    }
}
