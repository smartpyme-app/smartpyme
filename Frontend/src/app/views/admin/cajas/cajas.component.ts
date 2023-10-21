import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-cajas',
  templateUrl: './cajas.component.html'
})
export class CajasComponent implements OnInit {

    public cajas: any = [];
    public caja: any = {};
    public loading = false;
    public buscador:any = '';
    modalRef!: BsModalRef;

  	constructor( 
  	    private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
  	) { }

  	ngOnInit() {
  	    
       this.loadAll();
  	}

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('cajas').subscribe(cajas => {
            this.cajas = cajas;
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false;});
    }

    public printX(corte:any){
        window.open(this.apiService.baseUrl + '/api/corte/reporte/' + corte.id + '?token=' + this.apiService.auth_token(), 'Corte #' + corte.id, "top=50,left=300,width=600,height=500");
    }

    public printZ(caja:any){
        window.open(this.apiService.baseUrl + '/api/caja/reporte-dia/' + caja.id + '?token=' + this.apiService.auth_token(), 'Corte #' + caja.id, "top=50,left=300,width=600,height=500");
    }

    openModal(template: TemplateRef<any>, caja:any) {
        this.caja = caja;
        this.modalRef = this.modalService.show(template);
    }


    public onSubmit() {
        this.loading = true;
        this.caja.tipo = 'Tienda';
        this.apiService.store('caja', this.caja).subscribe(caja => {
          if (!this.caja.id)
              this.cajas.push(caja);
          this.caja = {};
          this.loading = false;
          this.alertService.success("Datos guardados");
          this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false; });
    }

      public delete(id:number) {
          if (confirm('¿Desea eliminar el Registro?')) {
              this.apiService.delete('caja/', id) .subscribe(data => {
                  for (let i = 0; i < this.cajas.length; i++) { 
                      if (this.cajas[i].id == data.id )
                          this.cajas.splice(i, 1);
                  }
              }, error => {this.alertService.error(error); });
                 
          }
      }

}
