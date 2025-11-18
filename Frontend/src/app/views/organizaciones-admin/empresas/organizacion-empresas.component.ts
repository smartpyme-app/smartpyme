import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';


@Component({
    selector: 'app-organizacion-empresas',
    templateUrl: './organizacion-empresas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    
})

export class OrganizacionEmpresasComponent extends BasePaginatedComponent implements OnInit {

    public empresas: PaginatedResponse<any> = {} as PaginatedResponse;
    public empresasList:any = [];
    public empresa:any = {};
    public saving:boolean = false;
    public override filtros:any = {};

    modalRef!: BsModalRef;

    constructor(apiService: ApiService, alertService: AlertService,
                private modalService: BsModalService
    ){
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.empresas;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.empresas = data;
    }

    ngOnInit() {

        this.loadAll();
    }

    public loadAll() {
        this.filtros.activo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.id_licencia = this.apiService.auth_user().empresa.licencia.id;
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarEmpresas();
    }

    public filtrarEmpresas(){
        this.apiService.getAll('licencias/empresas', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(empresas => { 
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
        this.apiService.store('empresa', empresa)
            .pipe(this.untilDestroyed())
            .subscribe(empresa => { 
                this.alertService.success('Empresa guardada', 'La empresa fue guardada exitosamente.');
            }, error => {this.alertService.error(error); });
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('empresa/', id)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                    for (let i = 0; i < this.empresas['data'].length; i++) { 
                        if (this.empresas['data'][i].id == data.id )
                            this.empresas['data'].splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
                   
        }

    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('licencia/empresa', this.empresa)
            .pipe(this.untilDestroyed())
            .subscribe(empresa => {
            this.loadAll();
            this.saving = false;
            if(!this.empresa.id){
                this.alertService.success('Empresa agregada', 'La empresa fue añadida exitosamente.');
            }else{
                this.alertService.success('Empresa guardada', 'La empresa fue guardada exitosamente.');
            }
            this.alertService.modal = false;
            this.modalRef?.hide();
        },error => {this.alertService.error(error); this.saving = false; });

    }

    // setPagination() ahora se hereda de BasePaginatedComponent


    openModal(template: TemplateRef<any>, empresa:any) {
        this.empresa = empresa;

        this.apiService.getAll('empresas/list')
            .pipe(this.untilDestroyed())
            .subscribe(empresasList => { 
                this.empresasList = empresasList;
            }, error => {this.alertService.error(error); });
        
        if (!this.empresa.id) {
            this.empresa.id_licencia = this.apiService.auth_user().empresa.licencia.id;
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }


}
