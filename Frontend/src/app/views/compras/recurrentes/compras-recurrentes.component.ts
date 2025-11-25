import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

declare var $:any;

@Component({
    selector: 'app-compras-recurrentes',
    templateUrl: './compras-recurrentes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PaginationComponent],

})

export class ComprasRecurrentesComponent extends BasePaginatedModalComponent implements OnInit {

    public compras: PaginatedResponse<any> = {} as PaginatedResponse;
    public compra:any = {};
    public formaPagos:any = [];
    public documentos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public buscador:any = '';
    public override saving:boolean = false;
    public downloading:boolean = false;

    public override filtros:any = {};

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.compras;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.compras = data;
    }

    ngOnInit() {
        this.loadAll();
        this.apiService.getAll('proveedores/list')
            .pipe(this.untilDestroyed())
            .subscribe(proveedores => { 
                this.proveedores = proveedores;
            }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_usuario = '';
        this.filtros.id_usuario = '';
        this.filtros.id_canal = '';
        this.filtros.id_documento = '';
        this.filtros.recurrente = true;
        this.filtros.forma_pago = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.filtrarCompras();
    }

    public filtrarCompras(){
        this.loading = true;
        this.apiService.getAll('compras', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(compras => { 
                this.compras = compras;
                this.loading = false;
                this.closeModal();
            }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarCompras();
    }

    public setEstado(compra:any, estado:any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la compra?')){
                this.compra = compra;
                this.compra.estado = estado;
                this.onSubmit();
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la compra?')){
                this.compra = compra;
                this.compra.estado = estado;
                this.onSubmit();
            }
        }

    }

    public async setRecurrencia(compra:any){
        this.compra = compra;
        this.compra.recurrente = false;
        
        try {
            const compraActualizada = await this.apiService.store('marcar-recurrente', this.compra)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            this.compra = {};
            this.loadAll();
            this.alertService.success('Compra guardada', 'La compra se marco como no recurrente exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
            this.saving = false;
        }
    }
    
    public async delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            try {
                const data = await this.apiService.delete('compra/', id)
                    .pipe(this.untilDestroyed())
                    .toPromise();
                
                for (let i = 0; i < this.compras['data'].length; i++) { 
                    if (this.compras['data'][i].id == data.id )
                        this.compras['data'].splice(i, 1);
                }
            } catch (error: any) {
                this.alertService.error(error);
            }
        }
    }

    public openModalEdit(template: TemplateRef<any>, compra:any) {
        this.compra = compra;

        this.apiService.getAll('documentos')
            .pipe(this.untilDestroyed())
            .subscribe(documentos => {
                this.documentos = documentos;
            }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago')
            .pipe(this.untilDestroyed())
            .subscribe(formaPagos => { 
                this.formaPagos = formaPagos;
            }, error => {this.alertService.error(error); });

        this.openModal(template);
    }


    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('compras/filtrar/' + filtro + '/', txt)
            .pipe(this.untilDestroyed())
            .subscribe(compras => { 
                this.compras = compras;
                this.loading = false;
            }, error => {this.alertService.error(error); });

    }

    public async onSubmit() {
        this.saving = true;
        try {
            const compraGuardada = await this.apiService.store('compra', this.compra)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            this.compra = {};
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.success('Venta guardado', 'La compra fue guardada exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.saving = false;
        }
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    public descargar(){
        this.downloading = true;
        this.apiService.export('compras/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'compras-recurrentes.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => {this.alertService.error(error); this.downloading = false;}
        );
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });

        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => { 
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error); });

        this.openModal(template);
    }

}
