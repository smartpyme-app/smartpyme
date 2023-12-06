import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto-precios',
  templateUrl: './producto-precios.component.html'
})
export class ProductoPreciosComponent implements OnInit {

    @Input() producto: any = {};
    public precio: any = {};
    public usuarios: any = [];
    public loading:boolean = false;
    public buscador:string = '';

    modalRef!: BsModalRef;

    constructor(private apiService: ApiService, private alertService: AlertService,  
        private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ){ }

    ngOnInit() {}

    openModal(template: TemplateRef<any>, precio:any) {
        this.precio = precio;

        this.apiService.store('usuarios/filtrar', { tipo : 'Ventas'}).subscribe(usuarios => { 
            this.usuarios = usuarios.data;
            // this.marcarTodos();
            this.loading = false;
        }, error => {this.alertService.error(error); });

        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    public marcarTodos(){
        this.usuarios.forEach((item:any) =>{
            item.autorizado = !item.autorizado;
        });
    }

    onSubmit(){
       
        this.loading = true;
        this.precio.id_producto = this.producto.id;
        this.precio.usuarios = this.usuarios;
        this.apiService.store('producto/precio', this.precio).subscribe(precio => {
            if(!this.precio.id) {
                this.precio.id = precio.id;
                this.producto.precios.unshift(precio);
                this.alertService.success('Precio creado', 'El precio fue añadido exitosamente.');
            }else{
                this.alertService.success('Precio guardado', 'El precio fue guardado exitosamente.');
            }
            this.precio = {};
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false;});

    }

    delete(precio:any){
        if (confirm('¿Desea eliminar el Registro?')) {        
            this.apiService.delete('producto/precio/', precio.id).subscribe(precio => {
                for (var i = 0; i < this.producto.precios.length; ++i) {
                    if (this.producto.precios[i].id === precio.id ){
                        this.producto.precios.splice(i, 1);
                    }
                }
            },error => {this.alertService.error(error); this.loading = false;});
        }
    }


}
