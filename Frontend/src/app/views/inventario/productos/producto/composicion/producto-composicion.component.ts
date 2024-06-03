import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto-composicion',
  templateUrl: './producto-composicion.component.html'
})
export class ProductoComposicionComponent implements OnInit {

    @Input() producto: any = {};
	public composicion: any = {};
    public productos:any = [];
    public opcion: any = {};
	public loading:boolean = false;
    public saving:boolean = false;
    public buscador:string = '';

	modalRef!: BsModalRef;

    constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {}

    openModal(template: TemplateRef<any>, compuesto:any) {
        this.apiService.getAll('productos/list').subscribe(productos => {
            this.productos = productos;
        }, error => {this.alertService.error(error);});
        
        if(compuesto.id){
            this.composicion = compuesto;
        }else{
            this.composicion.id_producto = this.producto.id;
            this.composicion.id_compuesto = '';
        }
        
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    onSubmit(){
       
        this.saving = true;
        this.apiService.store('producto/composicion', this.composicion).subscribe(composicion => {
            if(!this.composicion.id) {
                composicion.opciones = [];
                this.producto.composiciones.unshift(composicion);
            }
            this.composicion = {};
            this.saving = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.saving = false;});

    }

    delete(composicion:any){
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

    // Opciones

        public openModalOpciones(template: TemplateRef<any>, composicion:any) {
            this.composicion = composicion;
            this.apiService.getAll('productos/list').subscribe(productos => {
                this.productos = productos;
            }, error => {this.alertService.error(error);});

            this.modalRef = this.modalService.show(template, {class: 'modal-md'});
        }


        public agregarOpcion(){
            this.loading = true;
            this.opcion.id_composicion = this.composicion.id;
            this.apiService.store('producto/composicion/opcion', this.opcion).subscribe(opcion => {
                this.composicion.opciones.push(opcion);
                this.opcion = {};
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
        }

        public deleteOpcion(opcion:any){
            if (confirm('¿Desea eliminar el Registro?')) {
                this.apiService.delete('producto/composicion/opcion/', opcion.id).subscribe(opcion => {
                    for (let i = 0; i < this.composicion.opciones.length; i++) { 
                        if (this.composicion.opciones[i].id == opcion.id )
                            this.composicion.opciones.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
            }
        }


}
