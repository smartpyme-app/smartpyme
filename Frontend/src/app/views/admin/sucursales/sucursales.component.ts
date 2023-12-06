import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-sucursales',
  templateUrl: './sucursales.component.html'
})
export class SucursalesComponent implements OnInit {

    public sucursales: any[] = [];
    public sucursal: any = {};
    public loading = false;
    public buscador:any = '';
    public estado:any = '';

    modalRef!: BsModalRef;

  	constructor( 
  	    private apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
  	) { }

  	ngOnInit() {
  	    
        this.loadAll();

  	}

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('sucursales').subscribe(sucursales => {
            this.sucursales = sucursales;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    openModal(template: TemplateRef<any>, sucursal:any) {
        this.sucursal = sucursal;
        this.modalRef = this.modalService.show(template);
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('sucursal/', id) .subscribe(data => {
                for (let i = 0; i < this.sucursales.length; i++) { 
                    if (this.sucursales[i].id == data.id )
                        this.sucursales.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
               
        }
    }

    
    public onSubmit() {
          this.loading = true;
          // Guardamos la sucursal
          this.sucursal.empresa_id = this.apiService.auth_user().empresa_id;
          this.apiService.store('sucursal', this.sucursal).subscribe(sucursal => {
              if (!this.sucursal.id) {
                  this.sucursales.push(sucursal);
              }
              this.sucursal = {};
              this.alertService.success('Sucursal guardada', 'La sucursal fue guardada exitosamente.');
              this.loading = false;
            this.modalRef.hide();
          },error => {this.alertService.error(error); this.loading = false; });
      }

}
