import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

@Component({
    selector: 'app-proveedores',
    templateUrl: './proveedores.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent, ImportarExcelComponent],

})
export class ProveedoresComponent extends BasePaginatedModalComponent implements OnInit {

    public proveedores: PaginatedResponse<any> = {} as PaginatedResponse;
    public proveedor:any = {};
    public override loading:boolean = false;
    public override saving:boolean = false;
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
        return this.proveedores;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.proveedores = data;
    }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_categoria = '';
        this.filtros.buscador = '';
        this.filtros.estado = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtrarProveedores();

        // Ocultar modal de importación
        this.closeModal();
    }

    public filtrarProveedores(){
        this.loading = true;
        this.apiService.getAll('proveedores', this.filtros).subscribe(proveedores => {
            this.proveedores = proveedores;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setTipo(proveedor:any){
        this.proveedor = proveedor;
        this.onSubmit();
    }

    public setActivo(proveedor:any, estado:any){
        this.proveedor = proveedor;
        this.proveedor.enable = estado;
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('proveedor', this.proveedor).subscribe(proveedor => {
            this.proveedor = {};
            this.saving = false;
            this.alertService.success('Proveedor actualizado', 'El proveedor fue actualizado exitosamente.');
        }, error => {this.alertService.error(error); this.saving = false;});
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

    public delete(cliente:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('cliente/', cliente.id) .subscribe(data => {
                if (this.proveedores.data) {
                    for (let i = 0; i < this.proveedores.data.length; i++) {
                        if (this.proveedores.data[i].id == data.id )
                            this.proveedores.data.splice(i, 1);
                    }
                }
            }, error => {this.alertService.error(error); });

        }
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    override openModal(template: TemplateRef<any>) {
        super.openModal(template);
    }

    public descargarPersonas(){
        this.downloading = true;
        this.apiService.export('proveedores-personas/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'proveedores-personas.xlsx';
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
        this.apiService.export('proveedores-empresas/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'proveedores-empresas.xlsx';
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
