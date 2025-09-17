import { Component, OnInit, OnDestroy } from '@angular/core';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { FuncionalidadesService } from '@services/functionalities.service';

import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter  } from 'rxjs/operators';
import { Router, NavigationEnd } from '@angular/router';
import { filter as rxFilter } from 'rxjs/operators';

@Component({
  selector: 'app-sidebar',
  templateUrl: './sidebar.component.html'
})

export class SidebarComponent implements OnInit, OnDestroy {
    public sidebarCollapsed:boolean = false;

    public productosIsCollapsed:boolean = true;
    public ventasIsCollapsed:boolean = true;
    public comprasIsCollapsed:boolean = true;
    public preferenciasIsCollapsed:boolean = true;
    public finanzasIsCollapsed:boolean = true;
    public paquetesIsCollapsed:boolean = true;
    public planillaIsCollapsed:boolean = true;
    public lealtadClientesIsCollapsed:boolean = true;
    public usuario: any = {};
    public isVisible: boolean = false;
    public loading: boolean = false;
    public filtros: any = {};
    public items: any = [];
    public notificaciones: any = [];
    public authUser: any = {};
    public tieneFidelizacionHabilitada: boolean = false;

    searchControl = new FormControl();

    constructor(public apiService: ApiService, public alertService: AlertService, private funcionalidadesService: FuncionalidadesService, private router: Router) {}

    ngOnInit() {
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
        if (!localStorage.getItem('planillaIsCollapsed')) {
            localStorage.setItem('planillaIsCollapsed', this.planillaIsCollapsed.toString());
        }else{
            this.planillaIsCollapsed = JSON.parse(localStorage.getItem('planillaIsCollapsed')!);
        }
        if (!localStorage.getItem('paquetesIsCollapsed')) {
            localStorage.setItem('paquetesIsCollapsed', this.paquetesIsCollapsed.toString());
        }else{
            this.paquetesIsCollapsed = JSON.parse(localStorage.getItem('paquetesIsCollapsed')!);
        }
        if (!localStorage.getItem('lealtadClientesIsCollapsed')) {
            localStorage.setItem('lealtadClientesIsCollapsed', this.lealtadClientesIsCollapsed.toString());
        }else{
            this.lealtadClientesIsCollapsed = JSON.parse(localStorage.getItem('lealtadClientesIsCollapsed')!);
        }
        this.usuario = this.apiService.auth_user();

        this.searchControl.valueChanges
          .pipe(
            debounceTime(500),
            filter((query: string) => query.trim().length > 0),
            switchMap((query: any) => this.apiService.read('buscador/', query))
          )
          .subscribe((results: any[]) => {
            console.log(results);
            this.items = Array.isArray(results) ? results : [];
            this.loading = false;
          });

        this.loadNotificaciones();
        this.usuarioLogueado();
        this.verificarFidelizacionHabilitada();
        
        // Suscribirse a cambios de ruta para verificar funcionalidades cuando el usuario cambie
        this.router.events
            .pipe(rxFilter(event => event instanceof NavigationEnd))
            .subscribe(() => {
                // Verificar si el usuario ha cambiado (nuevo login)
                const currentUser = this.apiService.auth_user();
                if (currentUser && (!this.authUser || this.authUser.id !== currentUser.id)) {
                    this.usuarioLogueado();
                    this.verificarFidelizacionHabilitada();
                }
            });
    }


    toggleSidebar() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed.toString());

        if (this.sidebarCollapsed) {
            this.closeAll();
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
        if(this.productosIsCollapsed){
            this.closeAll();
        }
        this.productosIsCollapsed = !this.productosIsCollapsed;
        localStorage.setItem('productosIsCollapsed', this.productosIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    toggleVentas() {
        if(this.ventasIsCollapsed){
            this.closeAll();
        }
        this.ventasIsCollapsed = !this.ventasIsCollapsed;
        localStorage.setItem('ventasIsCollapsed', this.ventasIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    toggleCompras() {
        if(this.comprasIsCollapsed){
            this.closeAll();
        }
        this.comprasIsCollapsed = !this.comprasIsCollapsed;
        localStorage.setItem('comprasIsCollapsed', this.comprasIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    togglePreferencias() {
        if(this.preferenciasIsCollapsed){
            this.closeAll();
        }
        this.preferenciasIsCollapsed = !this.preferenciasIsCollapsed;
        localStorage.setItem('preferenciasIsCollapsed', this.preferenciasIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    toggleFinanzas() {
        if(this.finanzasIsCollapsed){
            this.closeAll();
        }
        this.finanzasIsCollapsed = !this.finanzasIsCollapsed;
        localStorage.setItem('finanzasIsCollapsed', this.finanzasIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    togglePlanilla() {
        if(this.planillaIsCollapsed){
            this.closeAll();
        }
        this.planillaIsCollapsed = !this.planillaIsCollapsed;
        localStorage.setItem('planillaIsCollapsed', this.planillaIsCollapsed.toString());
        this.toggleSidebarMenu();
    }

    togglePaquetes() {
        if(this.paquetesIsCollapsed){
            this.closeAll();
        }
        this.paquetesIsCollapsed = !this.paquetesIsCollapsed;
        localStorage.setItem('paquetesIsCollapsed', this.paquetesIsCollapsed.toString());
        this.toggleSidebarMenu();
    }


    toggleLealtadClientes() {
        if(this.lealtadClientesIsCollapsed){
            this.closeAll();    
        }
        this.lealtadClientesIsCollapsed = !this.lealtadClientesIsCollapsed;
        localStorage.setItem('lealtadClientesIsCollapsed', this.lealtadClientesIsCollapsed.toString());
        this.toggleSidebarMenu();
    }


    toggleSidebarMenu() {
        if (this.sidebarCollapsed) {
            this.sidebarCollapsed = false;
            localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed.toString());
        };
    }

    closeAll(){
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
        this.planillaIsCollapsed = true;
        localStorage.setItem('planillaIsCollapsed', this.planillaIsCollapsed.toString());
        this.paquetesIsCollapsed = true;
        localStorage.setItem('paquetesIsCollapsed', this.paquetesIsCollapsed.toString());
        this.lealtadClientesIsCollapsed = true;
        localStorage.setItem('lealtadClientesIsCollapsed', this.lealtadClientesIsCollapsed.toString());
    }

    public onSubmit(){
        this.loading = true;
        this.apiService.getAll('buscador', this.filtros).subscribe(items => { 
            this.items = items;
            this.loading = false;
        }, error => {this.alertService.error(error);this.loading = false; });
    }

    public loadNotificaciones() {
        this.filtros.leido = 0;
        this.filtros.paginate = 1;
        this.apiService.getAll('notificaciones', this.filtros).subscribe(notificaciones => { 
            this.notificaciones = notificaciones;
        }, error => {this.alertService.error(error); });
    }

    public usuarioLogueado() {
        this.authUser = this.apiService.auth_user();
    }

    private verificarFidelizacionHabilitada() {
        this.funcionalidadesService.verificarAcceso('fidelizacion-clientes').subscribe({
            next: (tieneAcceso: boolean) => {
                this.tieneFidelizacionHabilitada = tieneAcceso;
            },
            error: (error) => {
                console.error('Error al verificar acceso a fidelización:', error);
                this.tieneFidelizacionHabilitada = false;
            }
        });
    }

    ngOnDestroy() {
        // Limpiar suscripciones si es necesario
    }

}
