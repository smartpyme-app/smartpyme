import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-sucursales',
    templateUrl: './sucursales.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class SucursalesComponent implements OnInit {

    public sucursales:any = [];
    public sucursal:any = {};
    public loading = false;
    public saving = false;
    public sucursales_activas:any = 0;
    public filtros:any = {};

    modalRef!: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

  	constructor( 
  	    public apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
  	) { }

  	ngOnInit() {
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
  	    
        this.loadAll();

  	}

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('sucursales', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
            this.sucursales = sucursales;
            this.loading = false;
            this.contarActivos();
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    openModal(template: TemplateRef<any>, sucursal:any) {
        this.sucursal = sucursal;
        if(!this.sucursal.id){
            this.sucursal.id_empresa = this.apiService.auth_user().id_empresa;
            this.sucursal.activo = 1;
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg'});
    }

    closeModal(){
        this.modalRef.hide();
        this.alertService.modal = false;
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('sucursal/', id)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                for (let i = 0; i < this.sucursales.data.length; i++) { 
                    if (this.sucursales.data[i].id == data.id )
                        this.sucursales.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
               
        }
    }

    public contarActivos(){
        this.sucursales_activas = this.sucursales.data.filter((item:any) => item.activo == '1').length;
        console.log(this.sucursales_activas);
    }

    public setEstado(sucursal:any){
        this.apiService.store('sucursal', sucursal)
            .pipe(this.untilDestroyed())
            .subscribe(sucursal => { 
            if(sucursal.activo == '1'){
                this.alertService.success('Sucursal activada', 'La sucursal fue activada exitosamente.');
            }else{
                this.alertService.success('Sucursal desactivada', 'La sucursal fue desactivada exitosamente.');
            }
            this.contarActivos();
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    
    public onSubmit() {
          this.saving = true;
          this.apiService.store('sucursal', this.sucursal)
              .pipe(this.untilDestroyed())
              .subscribe(sucursal => {
              if (!this.sucursal.id) {
                    this.sucursales.data.push(sucursal);
                    this.alertService.success('Sucursal guardada', 'La sucursal fue añadida exitosamente.');
              }
              this.contarActivos();
              this.sucursal = {};
              this.saving = false;
            this.modalRef.hide();
            this.alertService.modal = false;
          },error => {this.alertService.error(error); this.saving = false; });
      }

}
