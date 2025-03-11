import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-admin-pagos',
  templateUrl: './admin-pagos.component.html'
})
export class AdminPagosComponent implements OnInit {

    public pagos:any = [];
    public productos:any = [];
    public pago:any = {};
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
        this.apiService.getAll('pagos', this.filtros).subscribe(pagos => {
            this.pagos = pagos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    openModal(template: TemplateRef<any>, pago:any) {
        this.pago = pago;
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
    }

    closeModal(){
        this.modalRef.hide();
        this.alertService.modal = false;
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('pago/', id) .subscribe(data => {
                for (let i = 0; i < this.pagos.data.length; i++) { 
                    if (this.pagos.data[i].id == data.id )
                        this.pagos.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
               
        }
    }


    public onSubmit() {
          this.saving = true;
          this.apiService.store('pago', this.pago).subscribe(pago => {
              if (!this.pago.id) {
                    this.pagos.data.push(pago);
                    this.alertService.success('Pago guardado', 'El pago fue añadido exitosamente.');
              }else{
                    this.alertService.success('Pago actualizado', 'El pago fue guardado exitosamente.');
                }
              this.pago = {};
              this.saving = false;
            this.modalRef.hide();
            this.alertService.modal = false;
          },error => {this.alertService.error(error); this.saving = false; });
      }

}
