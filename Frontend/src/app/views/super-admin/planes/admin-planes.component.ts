import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-admin-planes',
    templateUrl: './admin-planes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})
export class AdminPlanesComponent implements OnInit {

    public planes:any = [];
    public productos:any = [];
    public plan:any = {};
    public loading = false;
    public saving = false;
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
        this.apiService.getAll('planes', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(planes => {
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
            this.apiService.getAll('productos/list')
                .pipe(this.untilDestroyed())
                .subscribe(productos => {
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
            this.apiService.delete('plan/', id)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                    for (let i = 0; i < this.planes.data.length; i++) { 
                        if (this.planes.data[i].id == data.id )
                            this.planes.data.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
               
        }
    }


    public onSubmit() {
          this.saving = true;
          this.apiService.store('plan', this.plan)
              .pipe(this.untilDestroyed())
              .subscribe(plan => {
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
