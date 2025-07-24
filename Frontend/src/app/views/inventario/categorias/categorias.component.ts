import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-categorias',
  templateUrl: './categorias.component.html'
})

export class CategoriasComponent implements OnInit {

    public categorias:any = [];
    public categoria:any = {};
    public sucursales:any = [];
    public catalogo:any = [];
    public filtros:any = {};
    public loading:boolean = false;

    modalRef?: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('catalogo/list').subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});

        
    }

    public loadAll() {
        this.loading = true;
        this.filtros.estado = '';
        this.filtros.id_sucursal = '';

        this.filtrarCategorias();
    }

    public filtrarCategorias() {
        this.loading = true;
        this.apiService.getAll('categorias', this.filtros).subscribe(categorias => { 
            this.categorias = categorias;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }


    public openModal(template: TemplateRef<any>, categoria:any) {
        this.categoria = categoria;
        if (!this.categoria.id) {
            this.categoria.id_empresa = this.apiService.auth_user().id_empresa;
            this.categoria.enable = true;
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }

    public setEstado(categoria:any){
        this.categoria = categoria;
        this.onSubmit();
    }


    public onSubmit():void{
        this.loading = true;
        this.apiService.store('categoria', this.categoria).subscribe(categoria => {
            if (!this.categoria.id) {
                this.categorias.push(categoria);
                this.alertService.success('Categoria creada', 'La categoria fue añadida exitosamente.');
            }else{
                this.alertService.success('Categoria guardada', 'La categoria fue guardada exitosamente.');
            }
            this.alertService.modal = false;
            this.loadAll();
            this.loading = false;
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public delete(categoria:any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('categoria/', categoria.id) .subscribe(data => {
                for (let i = 0; i < this.categorias.length; i++) { 
                    if (this.categorias[i].id == data.id )
                        this.categorias.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }


    public verificarSiExiste(){
        if(this.categoria.nombre){
            if(this.categorias.filter( (item:any) => item.nombre.toLowerCase() == this.categoria.nombre.toLowerCase())[0]){
                this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.', 
                    'Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
                );
            }
        }
    }


}
