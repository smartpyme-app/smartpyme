import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

declare var $:any;

@Component({
    selector: 'app-gastos-recurrentes',
    templateUrl: './gastos-recurrentes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe],
    
})

export class GastosRecurrentesComponent extends BasePaginatedModalComponent implements OnInit {

    public gastos: PaginatedResponse<any> = {} as PaginatedResponse;
    public gasto:any = {};
    public formaPagos:any = [];
    public documentos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public buscador:any = '';
    public override saving:boolean = false;

    public override filtros:any = {};

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.gastos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.gastos = data;
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

        this.filtrarGastos();
    }

    public filtrarGastos(){
        this.loading = true;
        this.apiService.getAll('gastos', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe(gastos => { 
            this.gastos = gastos;
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

        this.filtrarGastos();
    }

    public setEstado(gasto:any, estado:any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la gasto?')){
                this.gasto = gasto;
                this.gasto.estado = estado;
                this.onSubmit();
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la gasto?')){
                this.gasto = gasto;
                this.gasto.estado = estado;
                this.onSubmit();
            }
        }

    }

    public setRecurrencia(gasto:any){
        this.gasto = gasto;
        this.gasto.recurrente = false;
        
        this.apiService.store('gasto', this.gasto)
          .pipe(this.untilDestroyed())
          .subscribe(gasto => {
            this.gasto = {};
            this.loadAll();
            this.alertService.success('Gasto guardada', 'La gasto se marco como no recurrente exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }
    
    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('gasto/', id)
              .pipe(this.untilDestroyed())
              .subscribe(data => {
                for (let i = 0; i < this.gastos['data'].length; i++) { 
                    if (this.gastos['data'][i].id == data.id )
                        this.gastos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public openModalEdit(template: TemplateRef<any>, gasto:any) {
        this.gasto = gasto;

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
        this.apiService.read('gastos/filtrar/' + filtro + '/', txt)
          .pipe(this.untilDestroyed())
          .subscribe(gastos => { 
            this.gastos = gastos;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public onSubmit() {
        this.saving = true;            
        this.apiService.store('gasto', this.gasto)
          .pipe(this.untilDestroyed())
          .subscribe(gasto => {
            this.gasto = {};
            this.saving = false;
            if(this.modalRef){
                this.closeModal();
            }
            this.alertService.success('Venta guardado', 'La gasto fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    public openDescargar(template: TemplateRef<any>) {
        this.openModal(template);
    }

    public descargarGastos(){
        this.apiService.export('gastos/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'gastos.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar gastos:', error); }
        );
    }

    public descargarDetalles(){
        this.apiService.export('gastos-detalles/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'gastos-detalles.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar gastos:', error); }
        );
    }

    public openAbono(template: TemplateRef<any>, gasto:any){
        this.gasto = gasto;
        this.openModal(template);
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
