import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-empresas',
  templateUrl: './empresas.component.html'
})

export class EmpresasComponent implements OnInit {

    public empresas:any = [];
    public empresa:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.filtros.activo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarEmpresas();
    }

    public filtrarEmpresas(){
        this.apiService.getAll('empresas', this.filtros).subscribe(empresas => { 
            this.empresas = empresas;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarEmpresas();
    }


    public setEstado(empresa:any){
        this.apiService.store('empresa', empresa).subscribe(empresa => { 
            this.alertService.success('Empresa guardada', 'La empresa fue guardada exitosamente.');
        }, error => {this.alertService.error(error); });
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('empresa/', id) .subscribe(data => {
                for (let i = 0; i < this.empresas['data'].length; i++) { 
                    if (this.empresas['data'][i].id == data.id )
                        this.empresas['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('empresa', this.empresa).subscribe(empresa => {
            this.loadAll();
            this.saving = false;
            if(!this.empresas.id){
                this.alertService.success('Empresa creada', 'La empresa fue añadida exitosamente.');
            }else{
                this.alertService.success('Empresa guardada', 'La empresa fue guardada exitosamente.');
            }
            this.modalRef?.hide();
        },error => {this.alertService.error(error); this.saving = false; });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.empresas.path + '?page='+ event.page, this.filtros).subscribe(empresas => { 
            this.empresas = empresas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


}
