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
    
    // Producto seleccionado del buscador
    public productoSeleccionado: any = null;

	modalRef!: BsModalRef;

    constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {}

    openModal(template: TemplateRef<any>, compuesto:any) {
        // Limpiar selección
        this.productoSeleccionado = null;
        
        if(compuesto.id){
            this.composicion = compuesto;
            // Si ya tiene un producto compuesto seleccionado, cargarlo
            if (compuesto.id_compuesto) {
                this.apiService.read('productos/', compuesto.id_compuesto).subscribe(producto => {
                    this.productoSeleccionado = producto;
                });
            }
        }else{
            this.composicion = {
                id_producto: this.producto.id,
                id_compuesto: '',
                cantidad: ''
            };
        }
        
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }
    
    productoSelect(producto: any) {
        this.productoSeleccionado = producto;
        this.composicion.id_compuesto = producto.id;
    }
    
    limpiarProducto() {
        this.productoSeleccionado = null;
        this.composicion.id_compuesto = '';
    }

    onSubmit(){
        if (!this.composicion.id_compuesto) {
            this.alertService.error('Debe seleccionar un producto');
            return;
        }
        
        if (!this.composicion.cantidad || this.composicion.cantidad <= 0) {
            this.alertService.error('Debe ingresar una cantidad válida');
            return;
        }
       
        this.saving = true;
        this.apiService.store('producto/composicion', this.composicion).subscribe(composicion => {
            if(!this.composicion.id) {
                composicion.opciones = [];
                this.producto.composiciones.unshift(composicion);
            } else {
                // Actualizar la composición existente en la lista
                const index = this.producto.composiciones.findIndex((c: any) => c.id === composicion.id);
                if (index !== -1) {
                    this.producto.composiciones[index] = composicion;
                }
            }
            this.composicion = {};
            this.productoSeleccionado = null;
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
            // Limpiar selección para opciones
            this.productoSeleccionado = null;
            this.opcion = {};

            this.modalRef = this.modalService.show(template, {class: 'modal-md'});
        }
        
        productoSelectOpcion(producto: any) {
            this.opcion.id_producto = producto.id;
            this.agregarOpcion();
        }
        
        limpiarProductoOpcion() {
            this.productoSeleccionado = null;
            this.opcion.id_producto = '';
        }


        public agregarOpcion(){
            if (!this.opcion.id_producto) {
                this.alertService.error('Debe seleccionar un producto');
                return;
            }
            
            this.loading = true;
            this.opcion.id_composicion = this.composicion.id;
            this.apiService.store('producto/composicion/opcion', this.opcion).subscribe(opcion => {
                this.composicion.opciones.push(opcion);
                this.opcion = {};
                this.productoSeleccionado = null;
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
