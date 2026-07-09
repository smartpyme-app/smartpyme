import { Component, OnDestroy, OnInit } from '@angular/core';
import { NavigationEnd, Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { Subject, takeUntil } from 'rxjs';
import { filter as rxFilter } from 'rxjs/operators';

@Component({
  selector: 'app-sidebar-ventas',
  templateUrl: './sidebar-ventas.component.html'
})

export class SidebarVentasComponent implements OnInit, OnDestroy {
    public sidebarCollapsed:boolean = false;

    public productosIsCollapsed:boolean = true;
    public ventasIsCollapsed:boolean = true;
    public comprasIsCollapsed:boolean = true;
    public preferenciasIsCollapsed:boolean = true;
    public finanzasIsCollapsed:boolean = true;
    public restauranteIsCollapsed:boolean = true;
    public pedidosIsCollapsed:boolean = true;
    public usuario: any = {};
    public isVisible: boolean = false;
    /** true cuando el dominio es abaco.smartpyme.site */
    public isAbacoSite: boolean = false;
    public tieneModuloRestaurante = false;
    public mostrarMenuRestaurante = false;
    public mostrarMenuPedidos = false;

    private destroy$ = new Subject<void>();

    constructor(
        public apiService: ApiService,
        private funcionalidadesService: FuncionalidadesService,
        private router: Router
    ) {}

    ngOnInit() {
        this.isAbacoSite = window.location.hostname === 'abaco.smartpyme.site';
        if (!localStorage.getItem('sidebarCollapsed')) {
            localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed.toString());
        }else{
            this.sidebarCollapsed = JSON.parse(localStorage.getItem('sidebarCollapsed')!);
        }
        if (!localStorage.getItem('productosIsCollapsed')) {
            localStorage.setItem('productosIsCollapsed', this.productosIsCollapsed.toString());
        }else{
            this.productosIsCollapsed = JSON.parse(localStorage.getItem('productosIsCollapsed')!);
        }
        if (!localStorage.getItem('ventasIsCollapsed')) {
            localStorage.setItem('ventasIsCollapsed', this.ventasIsCollapsed.toString());
        }else{
            this.ventasIsCollapsed = JSON.parse(localStorage.getItem('ventasIsCollapsed')!);
        }
        if (!localStorage.getItem('comprasIsCollapsed')) {
            localStorage.setItem('comprasIsCollapsed', this.comprasIsCollapsed.toString());
        }else{
            this.comprasIsCollapsed = JSON.parse(localStorage.getItem('comprasIsCollapsed')!);
        }
        if (!localStorage.getItem('preferenciasIsCollapsed')) {
            localStorage.setItem('preferenciasIsCollapsed', this.preferenciasIsCollapsed.toString());
        }else{
            this.preferenciasIsCollapsed = JSON.parse(localStorage.getItem('preferenciasIsCollapsed')!);
        }
        if (!localStorage.getItem('finanzasIsCollapsed')) {
            localStorage.setItem('finanzasIsCollapsed', this.finanzasIsCollapsed.toString());
        }else{
            this.finanzasIsCollapsed = JSON.parse(localStorage.getItem('finanzasIsCollapsed')!);
        }
        if (!localStorage.getItem('restauranteIsCollapsed')) {
            localStorage.setItem('restauranteIsCollapsed', this.restauranteIsCollapsed.toString());
        } else {
            this.restauranteIsCollapsed = JSON.parse(localStorage.getItem('restauranteIsCollapsed')!);
        }
        if (!localStorage.getItem('pedidosIsCollapsed')) {
            localStorage.setItem('pedidosIsCollapsed', this.pedidosIsCollapsed.toString());
        } else {
            this.pedidosIsCollapsed = JSON.parse(localStorage.getItem('pedidosIsCollapsed')!);
        }
        
        this.usuario = this.apiService.auth_user();
        this.verificarModuloRestauranteHabilitado();

        this.funcionalidadesService.onCambios()
            .pipe(takeUntil(this.destroy$))
            .subscribe(() => {
                this.verificarModuloRestauranteHabilitado();
            });

        this.router.events
            .pipe(
                rxFilter(event => event instanceof NavigationEnd),
                takeUntil(this.destroy$)
            )
            .subscribe(() => {
                this.actualizarMenusRestaurantePedidos();
            });
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
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
            }
        });
    }

    /** Funcionalidad activa en Super Admin + preferencia en empresa (custom_empresa) */
    private actualizarMenusRestaurantePedidos(): void {
        const vista = this.apiService.getVistaModuloRestaurantePedidos();
        const tieneFuncionalidad = this.tieneModuloRestaurante;
        this.mostrarMenuRestaurante = tieneFuncionalidad && (vista === 'restaurante' || vista === 'ambos');
        this.mostrarMenuPedidos = tieneFuncionalidad && (vista === 'pedidos' || vista === 'ambos');
    }


    toggleSidebar() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed.toString());

        if (this.sidebarCollapsed) {
            this.productosIsCollapsed = true;
            localStorage.setItem('productosIsCollapsed', this.productosIsCollapsed.toString());
            this.ventasIsCollapsed = true;
            localStorage.setItem('ventasIsCollapsed', this.ventasIsCollapsed.toString());
            this.comprasIsCollapsed = true;
            localStorage.setItem('comprasIsCollapsed', this.comprasIsCollapsed.toString());
            this.preferenciasIsCollapsed = true;
            localStorage.setItem('preferenciasIsCollapsed', this.preferenciasIsCollapsed.toString());
            this.finanzasIsCollapsed = true;
            localStorage.setItem('finanzasIsCollapsed', this.finanzasIsCollapsed.toString());
            this.restauranteIsCollapsed = true;
            localStorage.setItem('restauranteIsCollapsed', this.restauranteIsCollapsed.toString());
            this.pedidosIsCollapsed = true;
            localStorage.setItem('pedidosIsCollapsed', this.pedidosIsCollapsed.toString());
        };

    }


    toggleSidebarMin() {
        const myDiv = document.getElementById('sidebar')!;
        const toggleBtn = document.getElementById('toggleBtn')!;
        if (this.isVisible) {
          myDiv.style.marginLeft  = '-280px';
          toggleBtn.style.visibility  = 'visible';
        } else {
          myDiv.style.marginLeft  = '0px';
          toggleBtn.style.visibility  = 'hidden';
        }
        this.isVisible = !this.isVisible;

    }

    toggleProductos() {
        this.productosIsCollapsed = !this.productosIsCollapsed;
        localStorage.setItem('productosIsCollapsed', this.productosIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    toggleVentas() {
        this.ventasIsCollapsed = !this.ventasIsCollapsed;
        localStorage.setItem('ventasIsCollapsed', this.ventasIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    toggleCompras() {
        this.comprasIsCollapsed = !this.comprasIsCollapsed;
        localStorage.setItem('comprasIsCollapsed', this.comprasIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    togglePreferencias() {
        this.preferenciasIsCollapsed = !this.preferenciasIsCollapsed;
        localStorage.setItem('preferenciasIsCollapsed', this.preferenciasIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    toggleFinanzas() {
        this.finanzasIsCollapsed = !this.finanzasIsCollapsed;
        localStorage.setItem('finanzasIsCollapsed', this.finanzasIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    toggleRestaurante() {
        this.restauranteIsCollapsed = !this.restauranteIsCollapsed;
        localStorage.setItem('restauranteIsCollapsed', this.restauranteIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    togglePedidos() {
        this.pedidosIsCollapsed = !this.pedidosIsCollapsed;
        localStorage.setItem('pedidosIsCollapsed', this.pedidosIsCollapsed.toString());
        this.toggleSidebarMenu();
    }


    toggleSidebarMenu() {
        if (this.sidebarCollapsed) {
            this.sidebarCollapsed = false;
            localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed.toString());
        };
    }

}
