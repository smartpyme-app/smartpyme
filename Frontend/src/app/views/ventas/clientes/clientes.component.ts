import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

@Component({
    selector: 'app-clientes',
    templateUrl: './clientes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ImportarExcelComponent, PaginationComponent, TruncatePipe, PopoverModule, TooltipModule],

})
export class ClientesComponent extends BasePaginatedModalComponent implements OnInit {

    public clientes: PaginatedResponse<any> = {} as PaginatedResponse;
    public cliente:any = {};
    public downloading:boolean = false;

    public override filtros:any = {};
    public producto:any = {};
    public categorias:any = [];

    constructor( 
        apiService:ApiService, 
        alertService:AlertService, 
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.clientes;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.clientes = data;
    }

    ngOnInit() {

        this.loadAll();
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.tipo_contribuyente = '';
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.estado = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtrarClientes();

        // Ocultar modal de importación
        if(this.modalRef){
            this.closeModal();
        }
    }

    public filtrarClientes(){
        this.loading = true;
        this.apiService.getAll('clientes', this.filtros).subscribe(clientes => { 
            this.clientes = clientes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }
    
    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.loadAll();
    }

    public setTipo(cliente:any){
        this.cliente = cliente;
        this.onSubmit();
    }

    public setActivo(cliente:any, estado:any){
        this.cliente = cliente;
        this.cliente.enable = estado;
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('cliente', this.cliente).subscribe(cliente => {
            this.cliente = {};
            this.saving = false;
            this.alertService.success('Cliente actualizado', 'El cliente fue actualizado exitosamente.');
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public delete(cliente:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('cliente/', cliente.id) .subscribe(data => {
                for (let i = 0; i < this.clientes.data.length; i++) { 
                    if (this.clientes.data[i].id == data.id )
                        this.clientes.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }
    }

    // setPagination() ahora se hereda de BasePaginatedComponent
    // openModal() ahora se hereda de BasePaginatedModalComponent

    public descargarPersonas(){
        this.downloading = true;
        this.apiService.export('clientes-personas/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'clientes-personas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.alertService.modal = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; this.alertService.modal = false;}
        );
    }

    public descargarEmpresas(){
        this.downloading = true;
        this.alertService.modal = false;
        this.apiService.export('clientes-empresas/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'clientes-empresas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.alertService.modal = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; this.alertService.modal = false;}
        );
    }


    public descargarExtranjeros(){
        this.downloading = true;
        this.alertService.modal = false;
        this.apiService.export('clientes-extranjeros/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'clientes-extranjeros.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.alertService.modal = false;
        }, (error) => { this.alertService.error(error); this.downloading = false; this.alertService.modal = false;}
        );
    }


}
