import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto-proveedores',
  templateUrl: './producto-proveedores.component.html'
})
export class ProductoProveedoresComponent implements OnInit {

    @Input() producto: any = {};
    public proveedores: any = [];
    public proveedor: any = {};
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
        this.apiService.getAll('proveedores/list').subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }


    openModal(template: TemplateRef<any>, proveedor:any) {
        this.proveedor = proveedor;
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    public onSubmit() {
        this.loading = true;
        this.proveedor.id_producto = this.producto.id;
        this.apiService.store('producto/proveedor', this.proveedor).subscribe(proveedor => {
            if(!this.proveedor.id)
                this.producto.proveedores.push(proveedor);
            this.proveedor = {};
            this.loading = false;
            this.modalRef.hide();
            this.alertService.success("Proveedor agregado");
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public delete(proveedor:any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/proveedor/', proveedor.id) .subscribe(data => {
                for (let i = 0; i < this.producto.proveedores.length; i++) { 
                    if (this.producto.proveedores[i].id == data.id )
                        this.producto.proveedores.splice(i, 1);
                }
                this.alertService.success("Proveedor eliminado");
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
