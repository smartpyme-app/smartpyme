import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-admin-planes',
  templateUrl: './admin-planes.component.html'
})
export class AdminPlanesComponent implements OnInit {

    public planes:any = [];
    public productos:any = [];
    public plan:any = {};
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
        this.apiService.getAll('planes', this.filtros).subscribe(planes => {
            this.planes = planes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    openModal(template: TemplateRef<any>, plan:any) {
        this.plan = plan;
        if(!this.plan.id){
            this.plan.activo = 1;
        }

        if(!this.productos.length){
            this.apiService.getAll('productos/list').subscribe(productos => {
                this.productos = productos;
            }, error => {this.alertService.error(error);});
        }

        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
    }

    closeModal(){
        this.modalRef.hide();
        this.alertService.modal = false;
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('plan/', id) .subscribe(data => {
                for (let i = 0; i < this.planes.data.length; i++) { 
                    if (this.planes.data[i].id == data.id )
                        this.planes.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
               
        }
    }


    public onSubmit() {
          this.saving = true;
          this.apiService.store('plan', this.plan).subscribe(plan => {
              if (!this.plan.id) {
                    this.planes.data.push(plan);
                    this.alertService.success('Plan guardado', 'El plan fue añadida exitosamente.');
              }else{
                    this.alertService.success('Plan actualizado', 'El plan fue guardado exitosamente.');
                }
              this.plan = {};
              this.saving = false;
            this.modalRef.hide();
            this.alertService.modal = false;
          },error => {this.alertService.error(error); this.saving = false; });
      }

}
