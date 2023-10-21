import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto-inventarios',
  templateUrl: './producto-inventarios.component.html'
})
export class ProductoInventariosComponent implements OnInit {

    @Input() producto: any = {};
    public bodegas: any = [];
    public sucursal: any = {};
    public inventario: any = {};
    public sucursalSelected: any = {};
    public buscador:string = '';
    public loading:boolean = false;

    modalRef!: BsModalRef;

    constructor(private apiService: ApiService, private alertService: AlertService,  
        private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ){ }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('bodegas').subscribe(bodegas => {
            this.bodegas = bodegas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    public setAjuste(event:any){
        this.inventario.stock = event.stock_final;
    }


    openModal(template: TemplateRef<any>, inventario:any) {
        this.inventario = inventario;
        if (!this.inventario.id){
            this.inventario.stock = 0;
            // this.inventario.bodega_id = 1;
            this.inventario.producto_id = this.producto.id;
        }
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    public onSubmit() {
        this.loading = true;
        this.apiService.store('inventario', this.inventario).subscribe(inventario => {
            if(!this.inventario.id)
                this.producto.inventarios.push(inventario);
            this.inventario = {};
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('inventario/', id) .subscribe(data => {
                for (let i = 0; i < this.producto.inventarios.length; i++) { 
                    if (this.producto.inventarios[i].id == data.id )
                        this.producto.inventarios.splice(i, 1);
                }
                this.alertService.success("Registro eliminado");
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
