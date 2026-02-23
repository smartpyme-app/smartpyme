import { Component, OnInit, TemplateRef, Input, AfterViewInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

declare var bootstrap: any;

@Component({
  selector: 'app-producto-precios',
  templateUrl: './producto-precios.component.html'
})
export class ProductoPreciosComponent implements OnInit, AfterViewInit {

    @Input() producto: any = {};
    public precio: any = {};
    public usuarios: any = [];
    public loading:boolean = false;
    public buscador:string = '';

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,  
        private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ){ }

    ngOnInit() {}

    ngAfterViewInit() {
        // Inicializar tooltips de Bootstrap
        setTimeout(() => {
            this.initializeTooltips();
        }, 100);
    }

    private initializeTooltips() {
        // Destruir tooltips existentes
        const existingTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        existingTooltips.forEach(element => {
            const tooltip = bootstrap.Tooltip.getInstance(element);
            if (tooltip) {
                tooltip.dispose();
            }
        });

        // Inicializar nuevos tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    openModal(template: TemplateRef<any>, precio:any) {
        this.precio = precio;
        if(!this.precio.id){
            this.precio.clasificacion = null;
        }
        this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.loading = false;
        }, error => {this.alertService.error(error); });

        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    public marcarTodos(){
        this.usuarios.forEach((item:any) =>{
            item.autorizado = !item.autorizado;
        });
    }

    public calPrecioBase(){
        let iva = this.apiService.auth_user().empresa.iva;
        if(iva > 0){
            this.precio.impuesto = iva / 100;
            this.precio.precio = (this.precio.precio_final / (1 + (this.precio.impuesto * 1))).toFixed(4);
        }
    }

    public calPrecioFinal(){
        let iva = this.apiService.auth_user().empresa.iva;
        if(iva > 0){
            this.precio.impuesto = iva / 100;
            this.precio.precio_final = ((this.precio.precio * 1) + (this.precio.precio * this.precio.impuesto)).toFixed(2);
        }
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
            
            // Reinicializar tooltips después de agregar nuevo precio
            setTimeout(() => {
                this.initializeTooltips();
            }, 100);
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

    getUsuariosAutorizados(precio: any): string {
        if (!precio.usuarios || precio.usuarios.length === 0) {
            return 'Ninguno';
        }
        
        if (precio.usuarios.length === 1) {
            return precio.usuarios[0].nombre || 'Usuario';
        }
        
        if (precio.usuarios.length > 5) {
            return `${precio.usuarios.length} usuarios`;
        }
        
        return precio.usuarios.map((u: any) => u.nombre).join(', ');
    }

    getTooltipUsuarios(precio: any): string {
        if (!precio.usuarios || precio.usuarios.length === 0) {
            return 'Ningún usuario autorizado';
        }
        
        return precio.usuarios.map((u: any) => u.nombre).join(', ');
    }


}
