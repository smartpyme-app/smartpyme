import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
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
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';
import { FuncionalidadesService } from '@services/functionalities.service';

@Component({
    selector: 'app-clientes',
    templateUrl: './clientes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ImportarExcelComponent, PaginationComponent, TruncatePipe, PopoverModule, TooltipModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,

})
export class ClientesComponent extends BaseCrudComponent<any> implements OnInit {

    public clientes:any = {};
    public cliente:any = {};
    public downloading:boolean = false;
    public producto:any = {};
    public categorias:any = [];
    public tieneFidelizacionHabilitada: boolean = false;
    override modalRef!: BsModalRef;

    constructor(
        apiService:ApiService,
        alertService:AlertService,
        modalManager: ModalManagerService,
        private modalService: BsModalService,
        private cdr: ChangeDetectorRef,
        private funcionalidadesService: FuncionalidadesService

    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'cliente',
            itemsProperty: 'clientes',
            itemProperty: 'cliente',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El cliente fue actualizado exitosamente.',
                updated: 'El cliente fue actualizado exitosamente.',
                createTitle: 'Cliente actualizado',
                updateTitle: 'Cliente actualizado'
            },
            afterSave: () => {
                this.cliente = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarClientes();
    }

    ngOnInit() {
        this.verificarFidelizacionHabilitada();
        this.loadAll();
    }

    public override loadAll() {
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

    public async filtrarClientes(): Promise<void> {
        this.loading = true;
        this.cdr.markForCheck();
        try {
            this.clientes = await this.apiService.getAll('clientes', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise();
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        } finally {
            this.loading = false;
            this.cdr.markForCheck();
        }
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }
        this.cdr.markForCheck();
        this.loadAll();
    }

    public setTipo(cliente:any){
        this.cliente = cliente;
        this.cdr.markForCheck();
        this.onSubmit();
    }

    public setActivo(cliente:any, estado:any){
        this.cliente = cliente;
        this.cliente.enable = estado;
        this.cdr.markForCheck();
        this.onSubmit();
    }

    public override async delete(item: any | number): Promise<void> {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;

        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        try {
            const deletedItem = await this.apiService.delete('cliente/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();

            const index = this.clientes.data?.findIndex((c: any) => c.id === deletedItem.id);
            if (index !== -1 && index >= 0) {
                this.clientes.data.splice(index, 1);
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        } finally {
            this.loading = false;
            this.cdr.markForCheck();
        }
    }

  public override setPagination(event:any):void{
    this.loading = true;
    this.apiService.paginate(this.clientes.path + '?page='+ event.page).subscribe(clientes => {
      this.clientes = clientes;
      this.loading = false;
    }, error => {this.alertService.error(error); this.loading = false;});
  }

  override openModal(template: TemplateRef<any>) {
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template);
  }

    public async descargarPersonas(): Promise<void> {
        this.downloading = true;
        try {
            const data = await this.apiService.export('clientes-personas/exportar', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise() as Blob;
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'clientes-personas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.alertService.modal = false;
        } catch (error: any) {
            this.alertService.error(error);
            this.alertService.modal = false;
        } finally {
            this.downloading = false;
        }
    }

    public async descargarEmpresas(): Promise<void> {
        this.downloading = true;
        try {
            const data = await this.apiService.export('clientes-empresas/exportar', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise() as Blob;
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'clientes-empresas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.alertService.modal = false;
        } catch (error: any) {
            this.alertService.error(error);
            this.alertService.modal = false;
        } finally {
            this.downloading = false;
        }
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

    /**
     * Verificar si la empresa tiene fidelización habilitada
     */
    private verificarFidelizacionHabilitada(): void {
        this.funcionalidadesService.verificarAcceso('fidelizacion-clientes').subscribe({
            next: (tieneAcceso: boolean) => {
                this.tieneFidelizacionHabilitada = tieneAcceso;
            },
            error: (error) => {
                console.error('Error al verificar acceso a fidelización:', error);
                this.tieneFidelizacionHabilitada = false;
            }
        });
    }

    public generarEstadoCuenta(cliente: any){
        window.open(this.apiService.baseUrl + '/api/cliente/estado-de-cuenta/' + cliente.id + '?token=' + this.apiService.auth_token(), '_blank');
    }

}
