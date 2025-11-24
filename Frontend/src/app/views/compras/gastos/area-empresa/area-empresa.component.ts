import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-area-empresa',
    templateUrl: './area-empresa.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class AreaEmpresaComponent extends BaseCrudComponent<any> implements OnInit {

    public areas: any = {};
    public area: any = {};
    public departamento: any = {};
    public savingDepartamento: boolean = false;
    public downloading: boolean = false;
    public departamentoActual: any = null;
    public mostrarAdvertenciaDepartamento: boolean = false;
    private departamentoOriginal: string = '';

    public sucursales: any = [];
    public departamentos: any = [];
    public override filtros: any = {};

    modalRefDepartamento!: any; // BsModalRef

    constructor(
        protected override apiService: ApiService, 
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private route: ActivatedRoute
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'area-empresa',
            itemsProperty: 'areas',
            itemProperty: 'area',
            reloadAfterSave: true,
            reloadAfterDelete: true,
            messages: {
                created: 'El área fue creada exitosamente.',
                updated: 'El área fue actualizada exitosamente.',
                deleted: 'El área fue eliminada exitosamente.',
                createTitle: 'Área guardada',
                updateTitle: 'Área actualizada',
                deleteTitle: 'Área eliminada',
                deleteConfirm: '¿Está seguro de eliminar esta área? Esta acción no se puede deshacer.'
            },
            beforeSave: (item: any) => {
                // Validaciones
                if (!item.nombre || item.nombre.trim() === '') {
                    throw new Error('El nombre del área es requerido');
                }
                if (!item.id_departamento) {
                    throw new Error('Debe seleccionar un departamento');
                }
                // Asegurar que activo tenga un valor por defecto
                if (!item.activo) {
                    item.activo = '1';
                }
                return item;
            },
            afterSave: () => {
                this.resetArea();
            },
            initNewItem: (item: any) => {
                return {
                    id: null,
                    nombre: '',
                    descripcion: '',
                    id_sucursal: '',
                    id_departamento: '',
                    activo: '1'
                };
            }
        });
    }

    ngOnInit() {
        // Leer parámetros de la URL
        this.route.queryParams
          .pipe(this.untilDestroyed())
          .subscribe(params => {
            if (params['id_departamento']) {
                // Si viene con id_departamento en la URL, configurar filtros
                this.initializeFiltersWithDepartamento(params['id_departamento']);
            } else {
                // Carga normal sin filtros específicos
                this.loadAll();
            }
        });
        
        this.loadSucursales();
        this.loadDepartamentos();
    }

    private initializeFiltersWithDepartamento(idDepartamento: string) {
        this.filtros = {
            id_sucursal: '',
            id_departamento: idDepartamento,
            estado: '',
            buscador: '',
            inicio: '',
            fin: '',
            orden: 'created_at',
            direccion: 'desc',
            paginate: 10
        };
    
        this.loading = true;
        
        this.loadDepartamentos().then(() => {
            // Encontrar y guardar el departamento actual
            this.departamentoActual = this.departamentos.find((dept: any) => dept.id == idDepartamento);
            
            if (this.departamentoActual) {
                this.alertService.success(
                    'Filtro aplicado', 
                    `Mostrando áreas del departamento: ${this.departamentoActual.nombre}`
                );
            }
            this.aplicarFiltros();
        }).catch(() => {
            this.aplicarFiltros();
        });
    }

    public override loadAll() {
        // Inicializar filtros
        this.filtros = {
            id_sucursal: '',
            id_departamento: this.departamentoActual?.id,
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

        // Crear una copia de filtros para enviar
        const filtrosParaEnviar = { ...this.filtros };

        // Limpiar solo valores realmente vacíos, preservando el 0
        Object.keys(filtrosParaEnviar).forEach(key => {
            if (filtrosParaEnviar[key] === null || 
                filtrosParaEnviar[key] === undefined || 
                filtrosParaEnviar[key] === '') {
                delete filtrosParaEnviar[key];
            }
        });
        
        this.apiService.getAll('area-empresa', filtrosParaEnviar)
          .pipe(this.untilDestroyed())
          .subscribe(areas => { 
            this.areas = areas;
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

    public toggleEstado(area: any) {
        const nuevoEstado = area.activo == '1' || area.activo == 1 ? '0' : '1';
        const estadoTexto = nuevoEstado == '1' ? 'activada' : 'desactivada';
        
        if (confirm(`¿Está seguro de ${nuevoEstado == '1' ? 'activar' : 'desactivar'} esta área?`)) {
            area.activo = nuevoEstado;
            
            this.apiService.store('area-empresa', area)
              .pipe(this.untilDestroyed())
              .subscribe(response => { 
                this.alertService.success('Área actualizada', `El área fue ${estadoTexto} exitosamente.`);
                this.aplicarFiltros();
            }, error => {
                this.alertService.error(error);
                // Revertir el cambio en caso de error
                area.activo = area.activo == '1' ? '0' : '1';
            });
        }
    }

    public editArea(template: TemplateRef<any>, area: any) {
        this.openModal(template, area);
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    public descargar() {
        this.downloading = true;
        this.apiService.export('area-empresa/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data: Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'areas-empresa.xlsx';
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
        this.loadDepartamentos();
        this.openModal(template);
    }

    public override openModal(template: TemplateRef<any>, item?: any, config?: any) {
        this.loadSucursales();
        this.loadDepartamentos().then(() => {
            if (item) {
                // Si se pasa un item, usarlo
                super.openModal(template, item, config);
            } else {
                // Si no, crear uno nuevo
                this.resetArea();
                
                if (this.filtros.id_departamento) {
                    this.area.id_departamento = this.filtros.id_departamento;
                    this.departamentoOriginal = this.filtros.id_departamento;
                }
                
                super.openModal(template, undefined, config);
            }
        });
    }

    public openDepartamentoModal(template: TemplateRef<any>) {
        this.resetDepartamento();
        this.modalRefDepartamento = this.modalManager.openModal(template);
    }

    public onSucursalChange() {
        // Recargar departamentos cuando cambie la sucursal
        this.loadDepartamentos();
        this.area.id_departamento = ''; // Limpiar departamento seleccionado
    }

    public onFilterSucursalChange() {
        // Recargar departamentos para filtros cuando cambie la sucursal
        this.loadDepartamentos();
        this.filtros.id_departamento = ''; // Limpiar departamento seleccionado en filtros
    }

    public onSubmitDepartamento() {
        if (!this.departamento.nombre || this.departamento.nombre.trim() === '') {
            this.alertService.error('El nombre del departamento es requerido');
            return;
        }

        this.savingDepartamento = true;
        
        // Asegurar que activo tenga un valor por defecto
        if (!this.departamento.activo) {
            this.departamento.activo = '1';
        }

        // Asignar la sucursal del área si está seleccionada
        if (this.area.id_sucursal) {
            this.departamento.id_sucursal = this.area.id_sucursal;
        }
        
        this.apiService.store('departamentosEmpresa', this.departamento)
          .pipe(this.untilDestroyed())
          .subscribe(response => { 
            this.savingDepartamento = false;
            this.alertService.success('Departamento creado', 'El departamento fue creado exitosamente.');
            this.modalManager.closeModal(this.modalRefDepartamento);
            this.modalRefDepartamento = undefined;
            
            // Recargar departamentos y seleccionar el nuevo
            this.loadDepartamentos().then(() => {
                this.area.id_departamento = response.id;
            });
            
            this.resetDepartamento();
        }, error => {
            this.alertService.error(error);
            this.savingDepartamento = false;
        });
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

    private loadDepartamentos(): Promise<any> {
        return new Promise((resolve, reject) => {
            let params = {};
            
            // Si hay una sucursal seleccionada en el área o en filtros, filtrar por ella
            const sucursalId = this.area.id_sucursal || this.filtros.id_sucursal;
            if (sucursalId) {
                params = { id_sucursal: sucursalId };
            }
            
            this.apiService.getAll('area-empresa/list_departamentos', params)
              .pipe(this.untilDestroyed())
              .subscribe(departamentos => { 
                this.departamentos = departamentos;
                resolve(departamentos);
            }, error => {
                this.alertService.error(error);
                reject(error);
            });
        });
    }

    private resetArea() {
        this.area = {
            id: null,
            nombre: '',
            descripcion: '',
            id_sucursal: '',
            id_departamento: '',
            activo: '1'
        };
        this.mostrarAdvertenciaDepartamento = false;
        this.departamentoOriginal = '';
    }

    private resetDepartamento() {
        this.departamento = {
            nombre: '',
            descripcion: '',
            activo: '1'
        };
    }
    public limpiarFiltroDepartamento() {
        this.filtros.id_departamento = '';
        this.departamentoActual = null; // Limpiar departamento actual
        this.aplicarFiltros();
        this.alertService.info('Filtro removido', 'Mostrando todas las áreas');
    }

    public onDepartamentoChange() {
        // Mostrar advertencia si cambia del departamento original
        if (this.departamentoOriginal && this.area.id_departamento !== this.departamentoOriginal) {
            this.mostrarAdvertenciaDepartamento = true;
        } else {
            this.mostrarAdvertenciaDepartamento = false;
        }
    }

    public getNombreDepartamento(idDepartamento: string): string {
        const departamento = this.departamentos.find((dept: any) => dept.id == idDepartamento);
        return departamento?.nombre || '';
    }
}