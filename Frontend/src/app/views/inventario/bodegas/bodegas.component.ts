import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-bodegas',
  templateUrl: './bodegas.component.html'
})
export class BodegasComponent implements OnInit {

    public bodegas:any = [];
    public sucursales:any = [];
    public bodega:any = {};
    public loading = false;
    public saving = false;
    public filtros:any = {};

    modalRef!: BsModalRef;

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
        this.apiService.getAll('bodegas', this.filtros).subscribe(bodegas => {
            this.bodegas = bodegas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    openModal(template: TemplateRef<any>, bodega:any) {
        this.bodega = bodega;
        if(!this.bodega.id){
            this.bodega.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.bodega.id_empresa = this.apiService.auth_user().id_empresa;
            this.bodega.activo = 1;
        }

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); this.loading = false; });

        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
    }

    closeModal(){
        this.modalRef.hide();
        this.alertService.modal = false;
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('bodega/', id) .subscribe(data => {
                for (let i = 0; i < this.bodegas.data.length; i++) { 
                    if (this.bodegas.data[i].id == data.id )
                        this.bodegas.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
               
        }
    }

    public setEstado(bodega:any){
        this.apiService.store('bodega', bodega).subscribe(bodega => { 
            if(bodega.activo == '1'){
                this.alertService.success('Bodega activada', 'La bodega fue activada exitosamente.');
            }else{
                this.alertService.success('Bodega desactivada', 'La bodega fue desactivada exitosamente.');
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    
    public onSubmit() {
          this.saving = true;
          this.apiService.store('bodega', this.bodega).subscribe(bodega => {
              if (!this.bodega.id) {
                    this.bodegas.data.push(bodega);
                    this.alertService.success('Bodega guardada', 'La bodega fue añadida exitosamente.');
              }
              this.bodega = {};
              this.saving = false;
            this.modalRef.hide();
            this.alertService.modal = false;
          },error => {this.alertService.error(error); this.saving = false; });
      }

}
