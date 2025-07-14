import { Component, OnInit, TemplateRef } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-area-empresa',
  templateUrl: './area-empresa.component.html'
})

export class AreaEmpresaComponent implements OnInit {

    public areas: any = [];
    public area: any = {};
    public departamento: any = {};
    public loading: boolean = false;
    public saving: boolean = false;
    public savingDepartamento: boolean = false;
    public downloading: boolean = false;
    public departamentoActual: any = null;
    public mostrarAdvertenciaDepartamento: boolean = false;
    private departamentoOriginal: string = '';

    public sucursales: any = [];
    public departamentos: any = [];
    public filtros: any = {};

    modalRef!: BsModalRef;
    modalRefDepartamento!: BsModalRef;

    constructor(
        public apiService: ApiService, 
        private alertService: AlertService,
        private modalService: BsModalService,
        private route: ActivatedRoute // Agregar ActivatedRoute
    ) {}

    ngOnInit() {
        // Leer parámetros de la URL
        this.route.queryParams.subscribe(params => {
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
            this.filtrarArea();
        }).catch(() => {
            this.filtrarArea();
        });
    }

    public loadAll() {
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

        this.loading = true;
        this.filtrarArea();
    }

    public filtrarArea() {
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
        
        this.apiService.getAll('area-empresa', filtrosParaEnviar).subscribe(areas => { 
            this.areas = areas;
            this.loading = false;
            if (this.modalRef) {
                this.modalRef.hide();
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

        this.filtrarArea();
    }

    public toggleEstado(area: any) {
        const nuevoEstado = area.activo == '1' || area.activo == 1 ? '0' : '1';
        const estadoTexto = nuevoEstado == '1' ? 'activada' : 'desactivada';
        
        if (confirm(`¿Está seguro de ${nuevoEstado == '1' ? 'activar' : 'desactivar'} esta área?`)) {
            area.activo = nuevoEstado;
            this.saving = true;
            
            this.apiService.store('area-empresa', area).subscribe(response => { 
                this.saving = false;
                this.alertService.success('Área actualizada', `El área fue ${estadoTexto} exitosamente.`);
                this.filtrarArea();
            }, error => {
                this.alertService.error(error);
                this.saving = false;
                // Revertir el cambio en caso de error
                area.activo = area.activo == '1' ? '0' : '1';
            });
        }
    }

    public editArea(template: TemplateRef<any>, area: any) {
        this.area = { ...area }; // Crear una copia del área
        this.modalRef = this.modalService.show(template);
    }

    public onSubmit() {
        if (!this.area.nombre || this.area.nombre.trim() === '') {
            this.alertService.error('El nombre del área es requerido');
            return;
        }

        if (!this.area.id_departamento) {
            this.alertService.error('Debe seleccionar un departamento');
            return;
        }

        this.saving = true;
        
        // Asegurar que activo tenga un valor por defecto
        if (!this.area.activo) {
            this.area.activo = '1';
        }

        const action = this.area.id ? 'actualizada' : 'creada';
        
        this.apiService.store('area-empresa', this.area).subscribe(response => { 
            this.saving = false;
            this.alertService.success('Área guardada', `El área fue ${action} exitosamente.`);
            this.modalRef.hide();
            this.filtrarArea();
            this.resetArea();
        }, error => {
            this.alertService.error(error);
            this.saving = false;
        });
    }

    public delete(id: number) {
        if (confirm('¿Está seguro de eliminar esta área? Esta acción no se puede deshacer.')) {
            this.apiService.delete('area-empresa/', id).subscribe(data => {
                this.alertService.success('Área eliminada', 'El área fue eliminada exitosamente.');
                // Remover el elemento del array local
                if (this.areas && this.areas.data) {
                    this.areas.data = this.areas.data.filter((area: any) => area.id !== id);
                    this.areas.total--;
                }
            }, error => {
                this.alertService.error(error);
            });
        }
    }

    public setPagination(event: any): void {
        this.loading = true;
        this.apiService.paginate(this.areas.path + '?page=' + event.page, this.filtros).subscribe(areas => { 
            this.areas = areas;
            this.loading = false;
        }, error => {
            this.alertService.error(error); 
            this.loading = false;
        });
    }

    public descargar() {
        this.downloading = true;
        this.apiService.export('area-empresa/exportar', this.filtros).subscribe((data: Blob) => {
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
        this.modalRef = this.modalService.show(template);
    }

    public openModal(template: TemplateRef<any>) {
        this.loadSucursales();
        this.loadDepartamentos().then(() => {
            this.resetArea();
            
            if (this.filtros.id_departamento) {
                this.area.id_departamento = this.filtros.id_departamento;
                this.departamentoOriginal = this.filtros.id_departamento;
            }
            
            this.modalRef = this.modalService.show(template);
        });
    }

    public openDepartamentoModal(template: TemplateRef<any>) {
        this.resetDepartamento();
        this.modalRefDepartamento = this.modalService.show(template);
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
        
        this.apiService.store('departamentosEmpresa', this.departamento).subscribe(response => { 
            this.savingDepartamento = false;
            this.alertService.success('Departamento creado', 'El departamento fue creado exitosamente.');
            this.modalRefDepartamento.hide();
            
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
            this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
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
            
            this.apiService.getAll('area-empresa/list_departamentos', params).subscribe(departamentos => { 
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
        this.filtrarArea();
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