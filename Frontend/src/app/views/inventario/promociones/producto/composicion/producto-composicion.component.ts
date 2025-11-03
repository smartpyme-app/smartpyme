import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

@Component({
    selector: 'app-producto-composicion',
    templateUrl: './producto-composicion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class ProductoComposicionComponent implements OnInit {

    @Input() producto: any = {};
	public composicion: any = {};
    public productos:any = [];
	public loading:boolean = false;
    public buscador:string = '';

	modalRef!: BsModalRef;

    constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {}

    openModal(template: TemplateRef<any>, compuesto:any) {
        this.composicion = compuesto;
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    selectProducto(value:any){
        this.composicion.producto_id       = this.producto.id;
        this.composicion.nombre_compuesto  = value.nombre;
        this.composicion.compuesto_id      = value.id;
        this.composicion.medida            = value.medida;
        this.composicion.cantidad = 1;
        
        let detalle = this.producto.composiciones.find((x:any) => x.compuesto_id == this.composicion.compuesto_id);
        console.log(detalle);
        if(detalle){
            this.composicion = detalle;
        }

        this.productos.total = 0;
        document.getElementById('cantidad')!.focus();
    }


    onSubmit(){
       
        this.loading = true;
        this.apiService.store('producto/composicion', this.composicion).subscribe(composicion => {
            if(!this.composicion.id) {
                this.composicion.id = composicion.id;
                this.producto.composiciones.unshift(this.composicion);
            }
            this.composicion = {};
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false;});

    }

    deleteComposicion(composicion:any){
        if (confirm('¿Desea eliminar el Registro?')) {        
            this.apiService.delete('producto/composicion/', composicion.id).subscribe(composicion => {
                for (var i = 0; i < this.producto.composiciones.length; ++i) {
                    if (this.producto.composiciones[i].id === composicion.id ){
                        this.producto.composiciones.splice(i, 1);
                    }
                }
            },error => {this.alertService.error(error); this.loading = false;});
        }
    }


}
