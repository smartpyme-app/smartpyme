import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-paquetes',
    templateUrl: './paquetes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, ImportarExcelComponent],
    
})

export class PaquetesComponent extends BaseCrudComponent<any> implements OnInit {

    public paquetes:any = {};
    public sucursales:any = [];
    public clientes:any = [];
    public guias:any = [];
    public usuarios:any = [];
    public paquete:any = {};
    public downloading:boolean = false;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'paquete',
            itemsProperty: 'paquetes',
            itemProperty: 'paquete',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El paquete fue añadida exitosamente.',
                updated: 'El paquete fue guardada exitosamente.',
                createTitle: 'Paquete creada',
                updateTitle: 'Paquete guardada'
            },
            afterSave: (item, isNew) => {
                if (isNew) {
                    this.loadAll();
                }
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarPaquetes();
    }

    ngOnInit() {
        this.apiService.getAll('clientes/list')
            .pipe(this.untilDestroyed())
            .subscribe(clientes => { 
                this.clientes = clientes;
            }, error => {this.alertService.error(error); });

        this.getGuias();
        this.loadAll();
    }

    private getGuias() {   
        this.apiService.getAll('paquetes/list/guias')
            .pipe(this.untilDestroyed())
            .subscribe(paquetes => { 
                this.guias = paquetes;
            }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarPaquetes();
    }

    public override loadAll() {
        this.filtros.id_cliente = '';
        this.filtros.id_sucursal = '';
        this.filtros.id_asesor = '';
        this.filtros.id_usuario = '';
        this.filtros.tipo = '';
        this.filtros.estado = '';
        this.filtros.num_guia = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        if(this.apiService.validateRole('super_admin', false) || this.apiService.validateRole('admin', false)) {
            this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
        }
        
        this.filtrarPaquetes();
    }

    public filtrarPaquetes(){
        this.loading = true;
        if(!this.filtros.id_cliente){
            this.filtros.id_cliente = '';
        }

        if(!this.filtros.num_guia){
            this.filtros.num_guia = '';
        }
        this.apiService.getAll('paquetes', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(paquetes => { 
                this.paquetes = paquetes;
                this.loading = false;
                if(this.modalRef){
                    this.closeModal();
                }
            }, error => {this.alertService.error(error); this.loading = false;});
    }

    override openModal(template: TemplateRef<any>, paquete?: any) {
        super.openLargeModal(template, paquete);
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => { 
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error); });
        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });
        super.openLargeModal(template);
    }

    public setEstado(paquete:any){
        this.paquete = paquete;
        this.onSubmit();
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.loading = true;
                this.apiService.delete('paquete/', itemToDelete)
                    .pipe(this.untilDestroyed())
                    .subscribe({
                        next: (deletedItem: any) => {
                            const index = this.paquetes.data?.findIndex((p: any) => p.id === deletedItem.id);
                            if (index !== -1 && index >= 0) {
                                this.paquetes.data.splice(index, 1);
                            }
                            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
                            this.loading = false;
                        },
                        error: (error: any) => {
                            this.alertService.error(error);
                            this.loading = false;
                        }
                    });
          }
        });
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('paquetes/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'paquetes.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

}
