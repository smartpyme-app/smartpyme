import { Component, OnInit, Input, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-bodegas',
  templateUrl: './bodegas.component.html'
})
export class BodegasComponent implements OnInit {

    public bodegas: any[] = [];
    public sucursales: any[] = [];
    public bodega: any = {};
    public buscador:any = '';
    public loading = false;

    modalRef!: BsModalRef;

  	constructor( 
  	    public apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
  	) { }

  	ngOnInit() {
  	    
      this.loadAll();

    }
    
    public loadAll(){
        this.loading = true;
        this.apiService.getAll('bodegas').subscribe(bodegas => {
            this.bodegas = bodegas;
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false;});
        this.apiService.getAll('sucursales').subscribe(sucursales => {
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    openModal(template: TemplateRef<any>, bodega:any) {
        this.bodega = bodega;
        this.modalRef = this.modalService.show(template);
    }

    openModalData(template: TemplateRef<any>, bodega:any) {
        this.loading = true;
        this.apiService.read('bodega/', bodega.id).subscribe(data => {
            this.bodega = data;
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false;});
        this.modalRef = this.modalService.show(template);
    }

  	public onSubmit() {
  	    this.loading = true;
        this.apiService.store('bodega', this.bodega).subscribe(bodega => {
            if (!this.bodega.id)
                this.bodegas.push(bodega);
            this.bodega = {};
            this.loading = false;
            this.alertService.success("Datos guardados");
            this.modalRef.hide();
  	    },error => {this.alertService.error(error); this.loading = false; });
  	}

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('bodega/', id) .subscribe(data => {
                for (let i = 0; i < this.bodegas.length; i++) { 
                    if (this.bodegas[i].id == data.id )
                        this.bodegas.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
               
        }
    }

}
