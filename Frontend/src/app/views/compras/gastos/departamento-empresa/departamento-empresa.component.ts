import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { Router } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-departamento-empresa',
    templateUrl: './departamento-empresa.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent],
    
})

export class DepartamentoEmpresaComponent extends BaseCrudComponent<any> implements OnInit {

    public departamentos: any = {};
    public departamento: any = {};
    public downloading: boolean = false;

    public sucursales: any = [];
    public areas: any = [];
    public override filtros: any = {};

    @ViewChild('mdepartamento') modalTemplate!: TemplateRef<any>;

    constructor(
        protected override apiService: ApiService, 
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private router: Router
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'departamentosEmpresa',
            itemsProperty: 'departamentos',
            itemProperty: 'departamento',
            reloadAfterSave: true,
            reloadAfterDelete: true,
            messages: {
                created: 'El departamento fue creado exitosamente.',
                updated: 'El departamento fue actualizado exitosamente.',
                deleted: 'El departamento fue eliminado exitosamente.',
                createTitle: 'Departamento guardado',
                updateTitle: 'Departamento actualizado',
                deleteTitle: 'Departamento eliminado',
                deleteConfirm: '¿Está seguro de eliminar este departamento? Esta acción eliminará también todas las áreas asociadas y no se puede deshacer.'
            },
            beforeSave: (item: any) => {
                // Validaciones
                if (!item.nombre || item.nombre.trim() === '') {
                    throw new Error('El nombre del departamento es requerido');
                }
                if (!item.id_sucursal) {
                    throw new Error('Debe seleccionar una sucursal');
                }
                // Asegurar que estado tenga un valor por defecto
                if (!item.estado) {
                    item.estado = '1';
                }
                return item;
            },
            afterSave: () => {
                this.resetDepartamento();
            },
            initNewItem: (item: any) => {
                return {
                    id: null,
                    nombre: '',
                    descripcion: '',
                    id_sucursal: '',
                    estado: '1'
                };
            }
        });
    }

    ngOnInit() {
        this.loadAll();
        this.loadSucursales();
    }

    public override loadAll() {
        // Inicializar filtros
        this.filtros = {
            id_sucursal: '',
            estado: '',
            buscador: '',
            inicio: '',
            fin: '',
            orden: 'created_at',
            direccion: 'desc',
            paginate: 10
        };

        super.loadAll();
    }

    protected override aplicarFiltros(): void {
        this.loading = true;

        const filtrosParaEnviar = { ...this.filtros };

        // Limpiar valores vacíos
        Object.keys(filtrosParaEnviar).forEach(key => {
            if (filtrosParaEnviar[key] === null || 
                filtrosParaEnviar[key] === undefined || 
                filtrosParaEnviar[key] === '') {
                delete filtrosParaEnviar[key];
            }
        });
        
        this.apiService.getAll('departamentosEmpresa', filtrosParaEnviar)
          .pipe(this.untilDestroyed())
          .subscribe(departamentos => { 
            this.departamentos = departamentos;
            this.loading = false;
            if (this.modalRef) {
                this.closeModal();
            }
        }, error => {
            this.alertService.error(error); 
            this.loading = false;
        });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = columna;
            this.filtros.direccion = 'asc';
        }

        this.aplicarFiltros();
    }

    public toggleEstado(departamento: any) {
        const nuevoEstado = departamento.estado == '1' || departamento.estado == 1 ? '0' : '1';
        const estadoTexto = nuevoEstado == '1' ? 'activado' : 'desactivado';
        
        if (confirm(`¿Está seguro de ${nuevoEstado == '1' ? 'activar' : 'desactivar'} este departamento?`)) {
            departamento.estado = nuevoEstado;
            
            this.apiService.store('departamentosEmpresa/changeState/' + departamento.id, departamento)
              .pipe(this.untilDestroyed())
              .subscribe(response => { 
                this.alertService.success('Departamento actualizado', `El departamento fue ${estadoTexto} exitosamente.`);
                this.aplicarFiltros();
            }, error => {
                this.alertService.error(error);
                // Revertir el cambio en caso de error
                departamento.estado = departamento.estado == '1' ? '0' : '1';
            });
        }
    }

    public editDepartamento(template: TemplateRef<any>, departamento: any) {
        this.openModal(template, departamento);
    }

    public verAreas(departamento: any) {
        this.loadAreas(departamento.id);
        this.router.navigate(['/areas-empresa'], { queryParams: { id_departamento: departamento.id } });
        this.alertService.success('Áreas cargadas', `Se cargaron las áreas del departamento ${departamento.nombre}`);
    }

    public override async onSubmit(item?: any, isStatusChange: boolean = false): Promise<void> {
        const itemToSave = item || this.departamento;
        
        if (!itemToSave) {
            console.error('No se encontró el departamento a guardar');
            return;
        }

        // Validaciones
        if (!itemToSave.nombre || itemToSave.nombre.trim() === '') {
            this.alertService.error('El nombre del departamento es requerido');
            return;
        }

        if (!itemToSave.id_sucursal) {
            this.alertService.error('Debe seleccionar una sucursal');
            return;
        }

        // Asegurar que estado tenga un valor por defecto
        if (!itemToSave.estado) {
            itemToSave.estado = '1';
        }

        this.loading = true;
        this.saving = true;

        try {
            // Usar endpoint diferente para actualización
            const endpoint = itemToSave.id ? 'departamentosEmpresa/update' : 'departamentosEmpresa';
            const savedItem = await this.apiService.store(endpoint, itemToSave)
                .pipe(this.untilDestroyed())
                .toPromise();

            // Actualizar el item en el componente
            this.departamento = savedItem;

            // Actualizar en la lista
            if (itemToSave.id) {
                this.updateItemInList(savedItem);
            } else {
                this.addItemToList(savedItem);
            }

            // Mostrar mensaje de éxito
            const title = itemToSave.id ? this.config.messages!.updateTitle! : this.config.messages!.createTitle!;
            const message = itemToSave.id ? this.config.messages!.updated! : this.config.messages!.created!;
            this.alertService.success(title, message);

            // Recargar lista
            if (this.config.reloadAfterSave) {
                this.aplicarFiltros();
            }

            // Ejecutar callback afterSave
            if (this.config.afterSave) {
                this.config.afterSave(savedItem, !itemToSave.id);
            }

            // Cerrar modal si existe
            if (this.modalRef) {
                this.closeModal();
            }

        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
            this.saving = false;
        }
    }

    public descargar() {
        this.downloading = true;
        this.apiService.export('departamentosEmpresa/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data: Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'departamentos-empresa.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
        }, (error) => { 
            this.alertService.error(error); 
            this.downloading = false; 
        });
    }

    public openFilter(template: TemplateRef<any>) {
        this.loadSucursales();
        this.openModal(template);
    }

    public override openModal(template: TemplateRef<any>, item?: any, config?: any) {
        this.loadSucursales();
        super.openModal(template, item, config);
    }

    public onSucursalChange() {
        // Limpiar áreas cuando cambie la sucursal si las tienes cargadas
        this.areas = [];
    }

    public onFilterSucursalChange() {
        // Filtrar automáticamente cuando cambie la sucursal en filtros
        this.aplicarFiltros();
    }

    private loadSucursales() {
        if (!this.sucursales.length) {
            this.apiService.getAll('sucursales/list')
              .pipe(this.untilDestroyed())
              .subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {
                this.alertService.error(error);
            });
        }
    }

    private loadAreas(departamentoId: number) {
        this.apiService.getAll(`departamentosEmpresa/${departamentoId}/areas`)
          .pipe(this.untilDestroyed())
          .subscribe(areas => { 
            this.areas = areas;
        }, error => {
            this.alertService.error(error);
        });
    }

    private resetDepartamento() {
        this.departamento = {
            id: null,
            nombre: '',
            descripcion: '',
            id_sucursal: '',
            activo: '1'
        };
    }
}