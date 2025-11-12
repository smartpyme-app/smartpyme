import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-sidebar-servicios',
    templateUrl: './sidebar-servicios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, TooltipModule],
    
})

export class SidebarServiciosComponent implements OnInit {
    public sidebarCollapsed:boolean = false;

    public productosIsCollapsed:boolean = true;
    public ventasIsCollapsed:boolean = true;
    public comprasIsCollapsed:boolean = true;
    public preferenciasIsCollapsed:boolean = true;
    public finanzasIsCollapsed:boolean = true;
    public usuario: any = {};
    public isVisible: boolean = false;
    public modules: any[] = [];

    constructor(private apiService: ApiService) {}

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
        
        this.usuario = this.apiService.auth_user();
        this.loadModules();
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
        };

    }


    toggleSidebarMin() {
        // this.sidebarCollapsed = !this.sidebarCollapsed;
        // localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed.toString());

        // if (this.sidebarCollapsed) {
        //     this.productosIsCollapsed = true;
        //     localStorage.setItem('productosIsCollapsed', this.productosIsCollapsed.toString());
        //     this.ventasIsCollapsed = true;
        //     localStorage.setItem('ventasIsCollapsed', this.ventasIsCollapsed.toString());
        //     this.comprasIsCollapsed = true;
        //     localStorage.setItem('comprasIsCollapsed', this.comprasIsCollapsed.toString());
        //     this.preferenciasIsCollapsed = true;
        //     localStorage.setItem('preferenciasIsCollapsed', this.preferenciasIsCollapsed.toString());
        //     this.finanzasIsCollapsed = true;
        //     localStorage.setItem('finanzasIsCollapsed', this.finanzasIsCollapsed.toString());
        // };

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


    toggleSidebarMenu() {
        if (this.sidebarCollapsed) {
            this.sidebarCollapsed = false;
            localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed.toString());
        };
    }

    canShowOption(permission: string): boolean {
        return this.apiService.hasPermission(permission);
    }

    loadModules() {
        this.apiService.getModules().subscribe(modules => {
            this.modules = modules;
        });
    }

}
