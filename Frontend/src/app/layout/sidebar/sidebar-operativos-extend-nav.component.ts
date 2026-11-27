import { Component, DestroyRef, Input, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { CollapseModule } from 'ngx-bootstrap/collapse';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { LibroIvaPaisService } from '@views/contabilidad/libro-iva-shared/libro-iva-pais.service';
import { CountryI18nService } from '@services/country-i18n.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { LazyImageDirective } from '../../directives/lazy-image.directive';
import { RestauranteSidebarLinksComponent } from './restaurante-sidebar-links.component';
import {
    mostrarMenuCompras,
    mostrarMenuGastos,
    puedeVerRegistrosCompras,
    puedeVerRegistrosGastos,
    mostrarSeccionProductosExpandida,
} from './sidebar-operativos-nav.shared';
import { SLUG_CREDITOS_CLIENTES } from '@views/ventas/creditos/creditos-acceso';
import { puedeVerMenuPrestamos, SLUG_PRESTAMOS_EMPRESA } from '@views/finanzas/prestamos/prestamos-acceso';
import {
    mostrarMenuLicenciasOrganizacion as evaluarMenuLicenciasOrganizacion,
} from '@views/ventas/solicitudes-compra/solicitudes-compra-acceso';

/** Módulos del menú operativo completo; en sidebar ventas evita duplicar entradas ya fijas. */
@Component({
    selector: 'app-sidebar-operativos-extend-nav',
    host: { style: 'display: contents' },
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        CollapseModule,
        TooltipModule,
        LazyImageDirective,
        RestauranteSidebarLinksComponent,
    ],
    templateUrl: './sidebar-operativos-extend-nav.component.html',
})
export class SidebarOperativosExtendNavComponent implements OnInit {
    @Input() usuario: { empresa?: { licencia?: unknown; es_empresa_padre?: boolean } } = {};
    /** true dentro de app-sidebar-ventas (u otros sidebars acotados). */
    @Input() enSidebarVentas = false;

    public comprasIsCollapsed = true;
    public gastosIsCollapsed = true;
    public productosIsCollapsed = true;
    public finanzasIsCollapsed = true;
    public planillaIsCollapsed = true;
    public contabilidadIsCollapsed = true;
    public lealtadClientesIsCollapsed = true;
    public licenciasIsCollapsed = true;
    public restauranteIsCollapsed = true;
    public pedidosIsCollapsed = true;
    public despachoIsCollapsed = true;

    public contabilidadHabilitada = false;
    public tieneFidelizacionHabilitada = false;
    public tieneComisionesHabilitada = false;
    public tieneBonosHabilitada = false;
    public tieneGiftCardsHabilitada = false;
    public tieneCreditosHabilitada = false;
    public tienePrestamosHabilitada = false;
    public tieneDescargaDtesHabilitada = false;
    public tieneModuloRestaurante = false;
    public mostrarMenuRestaurante = false;
    public mostrarMenuPedidos = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(
        public apiService: ApiService,
        private funcionalidadesService: FuncionalidadesService,
        private libroIvaPais: LibroIvaPaisService,
        private countryI18n: CountryI18nService,
    ) {}

    ngOnInit(): void {
        this.hydrateCollapsedFromStorage();
        this.verificarAccesoContabilidad();
        this.verificarFidelizacionHabilitada();
        this.verificarComisionesHabilitada();
        this.verificarBonosHabilitada();
        this.verificarGiftCardsHabilitada();
        this.verificarCreditosHabilitada();
        this.verificarPrestamosHabilitada();
        this.verificarDescargaDtesHabilitada();
        this.verificarModuloRestauranteHabilitado();

        this.funcionalidadesService.onCambios().pipe(this.untilDestroyed()).subscribe(() => {
            this.verificarAccesoContabilidad();
            this.verificarFidelizacionHabilitada();
            this.verificarComisionesHabilitada();
            this.verificarBonosHabilitada();
            this.verificarGiftCardsHabilitada();
            this.verificarCreditosHabilitada();
            this.verificarPrestamosHabilitada();
            this.verificarDescargaDtesHabilitada();
            this.verificarModuloRestauranteHabilitado();
        });

        this.apiService.sesionEmpresaActualizada$.pipe(this.untilDestroyed()).subscribe(() => {
            this.verificarModuloRestauranteHabilitado();
        });
    }

    private hydrateCollapsedFromStorage(): void {
        const keys = [
            ['comprasIsCollapsed', 'comprasIsCollapsed'],
            ['gastosIsCollapsed', 'gastosIsCollapsed'],
            ['productosIsCollapsed', 'productosIsCollapsed'],
            ['finanzasIsCollapsed', 'finanzasIsCollapsed'],
            ['planillaIsCollapsed', 'planillaIsCollapsed'],
            ['contabilidadIsCollapsed', 'contabilidadIsCollapsed'],
            ['lealtadClientesIsCollapsed', 'lealtadClientesIsCollapsed'],
            ['licenciasIsCollapsed', 'licenciasIsCollapsed'],
            ['restauranteIsCollapsed', 'restauranteIsCollapsed'],
            ['pedidosIsCollapsed', 'pedidosIsCollapsed'],
            ['despachoIsCollapsed', 'despachoIsCollapsed'],
        ] as const;
        for (const [field, storageKey] of keys) {
            const raw = localStorage.getItem(storageKey);
            if (raw) {
                this[field] = JSON.parse(raw) as boolean;
            }
        }
    }

    private flipCollapsed(prop: 'comprasIsCollapsed' | 'gastosIsCollapsed' | 'productosIsCollapsed' | 'finanzasIsCollapsed' | 'planillaIsCollapsed' | 'contabilidadIsCollapsed' | 'lealtadClientesIsCollapsed' | 'licenciasIsCollapsed' | 'restauranteIsCollapsed' | 'pedidosIsCollapsed' | 'despachoIsCollapsed'): void {
        this[prop] = !this[prop];
        localStorage.setItem(prop, JSON.stringify(this[prop]));
    }

    toggleCompras(): void { this.flipCollapsed('comprasIsCollapsed'); }
    toggleGastos(): void { this.flipCollapsed('gastosIsCollapsed'); }
    toggleProductos(): void { this.flipCollapsed('productosIsCollapsed'); }
    toggleFinanzas(): void { this.flipCollapsed('finanzasIsCollapsed'); }
    togglePlanilla(): void { this.flipCollapsed('planillaIsCollapsed'); }
    toggleContabilidad(): void { this.flipCollapsed('contabilidadIsCollapsed'); }
    toggleLealtadClientes(): void { this.flipCollapsed('lealtadClientesIsCollapsed'); }
    toggleLicencias(): void { this.flipCollapsed('licenciasIsCollapsed'); }
    toggleRestaurante(): void { this.flipCollapsed('restauranteIsCollapsed'); }
    togglePedidos(): void { this.flipCollapsed('pedidosIsCollapsed'); }
    toggleDespacho(): void { this.flipCollapsed('despachoIsCollapsed'); }

    isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }

    get libroIvaRoute(): string[] {
        return this.libroIvaPais.rutaInicioLibroIva();
    }

    get libroIvaMenuLabel(): string {
        return this.countryI18n.libroIva('menuLabel');
    }

    get feDownloadSidebarLabel(): string {
        return this.countryI18n.fe('downloadSidebarLabel');
    }

    get mostrarMenuCompras(): boolean {
        return mostrarMenuCompras(
            (p) => this.apiService.hasPermission(p),
            (ps) => this.apiService.hasAnyPermission(ps),
            () => this.apiService.canAccederOperacionesInventario(),
        );
    }

    get mostrarMenuGastos(): boolean {
        return mostrarMenuGastos((p) => this.apiService.hasPermission(p));
    }

    puedeVerRegistrosCompras(): boolean {
        return puedeVerRegistrosCompras((p) => this.apiService.hasPermission(p));
    }

    puedeVerRegistrosGastos(): boolean {
        return puedeVerRegistrosGastos((p) => this.apiService.hasPermission(p));
    }

    get mostrarProductosExpandido(): boolean {
        return mostrarSeccionProductosExpandida(
            (p) => this.apiService.hasPermission(p),
            this.enSidebarVentas,
        );
    }

    get tieneIncentivosHabilitada(): boolean {
        return this.tieneComisionesHabilitada || this.tieneBonosHabilitada || this.tieneGiftCardsHabilitada;
    }

    get mostrarMenuPlanilla(): boolean {
        return (
            this.apiService.hasPermission('planilla.ver') ||
            this.apiService.hasPermission('planilla.registros.ver') ||
            this.apiService.hasPermission('planilla.empleados.ver') ||
            this.apiService.hasPermission('planilla.configuracion.ver') ||
            (this.tieneComisionesHabilitada && this.apiService.hasPermission('planilla.comisiones.ver')) ||
            (this.tieneBonosHabilitada && this.apiService.hasPermission('planilla.bonos.ver')) ||
            (this.tieneIncentivosHabilitada && this.apiService.hasPermission('planilla.incentivos.ver'))
        );
    }

    get enlacePlanillas(): string {
        if (this.apiService.hasPermission('planilla.registros.ver')) {
            return '/planilla';
        }
        if (this.apiService.hasPermission('planilla.empleados.ver')) {
            return '/planilla/empleados';
        }
        if (this.apiService.hasPermission('planilla.configuracion.ver')) {
            return '/planilla/configuracion-planilla';
        }
        if (this.tieneComisionesHabilitada && this.apiService.hasPermission('planilla.comisiones.ver')) {
            return '/comisiones/configuracion';
        }
        if (this.tieneBonosHabilitada && this.apiService.hasPermission('planilla.bonos.ver')) {
            return '/bonos/reglas';
        }
        if (this.tieneIncentivosHabilitada && this.apiService.hasPermission('planilla.incentivos.ver')) {
            return '/incentivos/vendedores';
        }
        return '/planilla';
    }

    get mostrarMenuPrestamos(): boolean {
        return puedeVerMenuPrestamos(
            this.contabilidadHabilitada,
            this.tienePrestamosHabilitada,
            this.apiService.hasPermission('finanzas.prestamos.ver'),
        );
    }

    get mostrarMenuLicenciasOrganizacion(): boolean {
        return evaluarMenuLicenciasOrganizacion(this.usuario?.empresa, (perm) =>
            this.apiService.hasPermission(perm),
        );
    }

    private verificarAccesoContabilidad(): void {
        this.funcionalidadesService.verificarAcceso('contabilidad').subscribe({
            next: (acceso) => {
                this.contabilidadHabilitada = acceso;
            },
            error: () => {
                this.contabilidadHabilitada = false;
            },
        });
    }

    private verificarFidelizacionHabilitada(): void {
        this.funcionalidadesService.verificarAcceso('fidelizacion-clientes').subscribe({
            next: (ok) => {
                this.tieneFidelizacionHabilitada = ok && this.apiService.isFidelizacionActiva();
            },
            error: () => {
                this.tieneFidelizacionHabilitada = false;
            },
        });
    }

    private verificarComisionesHabilitada(): void {
        this.funcionalidadesService.verificarAcceso('comisiones').subscribe({
            next: (ok) => {
                this.tieneComisionesHabilitada = ok;
            },
            error: () => {
                this.tieneComisionesHabilitada = false;
            },
        });
    }

    private verificarBonosHabilitada(): void {
        this.funcionalidadesService.verificarAcceso('bonos').subscribe({
            next: (ok) => {
                this.tieneBonosHabilitada = ok;
            },
            error: () => {
                this.tieneBonosHabilitada = false;
            },
        });
    }

    private verificarGiftCardsHabilitada(): void {
        this.funcionalidadesService.verificarAcceso('gift-cards').subscribe({
            next: (ok) => {
                this.tieneGiftCardsHabilitada = ok;
            },
            error: () => {
                this.tieneGiftCardsHabilitada = false;
            },
        });
    }

    private verificarCreditosHabilitada(): void {
        this.funcionalidadesService.verificarAcceso(SLUG_CREDITOS_CLIENTES).subscribe({
            next: (ok) => {
                this.tieneCreditosHabilitada = ok;
            },
            error: () => {
                this.tieneCreditosHabilitada = false;
            },
        });
    }

    private verificarPrestamosHabilitada(): void {
        this.funcionalidadesService.verificarAcceso(SLUG_PRESTAMOS_EMPRESA).subscribe({
            next: (ok) => {
                this.tienePrestamosHabilitada = ok;
            },
            error: () => {
                this.tienePrestamosHabilitada = false;
            },
        });
    }

    private verificarDescargaDtesHabilitada(): void {
        this.funcionalidadesService.verificarAcceso('descarga-automatizada-dtes').subscribe({
            next: (ok) => {
                this.tieneDescargaDtesHabilitada = ok;
            },
            error: () => {
                this.tieneDescargaDtesHabilitada = false;
            },
        });
    }

    private verificarModuloRestauranteHabilitado(): void {
        this.funcionalidadesService.verificarAcceso('modulo-restaurante').subscribe({
            next: (tieneAcceso) => {
                this.tieneModuloRestaurante = tieneAcceso;
                this.actualizarMenusRestaurantePedidos();
            },
            error: () => {
                this.tieneModuloRestaurante = false;
                this.actualizarMenusRestaurantePedidos();
            },
        });
    }

    private actualizarMenusRestaurantePedidos(): void {
        const vista = this.apiService.getVistaModuloRestaurantePedidos();
        const tieneFuncionalidad = this.tieneModuloRestaurante;
        this.mostrarMenuRestaurante = tieneFuncionalidad && (vista === 'restaurante' || vista === 'ambos');
        this.mostrarMenuPedidos = tieneFuncionalidad && (vista === 'pedidos' || vista === 'ambos');
    }
}