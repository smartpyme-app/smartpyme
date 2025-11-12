import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { GastosCategoriasComponent } from '../../compras/gastos/categorias/gastos-categorias.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-contabilidad-configuracion',
    templateUrl: './contabilidad-configuracion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, GastosCategoriasComponent, TooltipModule],
    
})
export class ContabilidadConfiguracionComponent implements OnInit {

    public configuracion: any = {};
    public cuentas: any = {};
    public catalogo: any = [];
    public loading = false;
    public loadingCatalogo = false;
    public saving = false;
    modalRef!: BsModalRef;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ) { 
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.loadingCatalogo = true;
        this.loading = true;
        
        // Cargar catálogo y configuración en paralelo
        this.apiService.getAll('catalogo/list').subscribe(catalogo => {
            this.catalogo = catalogo;
            this.loadingCatalogo = false;
        }, error => {
            this.alertService.error(error);
            this.loadingCatalogo = false;
        });

        this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.apiService.read('contabilidad/configuracion/', this.apiService.auth_user().id_empresa).subscribe(configuracion => {
            this.configuracion = configuracion;
            if (!this.configuracion.id) {
                this.configuracion = {};
                this.configuracion.id_empresa = this.apiService.auth_user().id_empresa;
            }
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    // Función trackBy para mejorar el rendimiento de ngFor
    trackByCuentaId(index: number, cuenta: any): any {
        return cuenta ? cuenta.id : index;
    }

        public onSubmit() {
            this.saving = true;
            this.apiService.store('contabilidad/configuracion', this.configuracion).subscribe(configuracion => {
                if (!this.configuracion.id) {
                    this.alertService.success('Configuracion creada', 'El configuracion fue añadido exitosamente.');
                }else{
                    this.alertService.success('Configuracion guardada', 'El configuracion fue guardado exitosamente.');
                }
                this.saving = false;
            }, error => {this.alertService.error(error); this.saving = false; });
        }


}
