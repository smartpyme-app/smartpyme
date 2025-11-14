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
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

@Component({
    selector: 'app-departamento-empresa',
    templateUrl: './departamento-empresa.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent],
    
})

export class DepartamentoEmpresaComponent extends BasePaginatedModalComponent implements OnInit {

    public departamentos: PaginatedResponse<any> = {} as PaginatedResponse;
    public departamento: any = {};
    public override saving: boolean = false;
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
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.departamentos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.departamentos = data;
    }

    ngOnInit() {
        this.loadAll();
        this.loadSucursales();
    }

    public loadAll() {
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

        this.loading = true;
        this.filtrarDepartamento();
    }

    public filtrarDepartamento() {
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
        
        this.apiService.getAll('departamentosEmpresa', filtrosParaEnviar).subscribe(departamentos => { 
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

        this.filtrarDepartamento();
    }

    public toggleEstado(departamento: any) {
        const nuevoEstado = departamento.estado == '1' || departamento.estado == 1 ? '0' : '1';
        const estadoTexto = nuevoEstado == '1' ? 'activado' : 'desactivado';
        
        if (confirm(`¿Está seguro de ${nuevoEstado == '1' ? 'activar' : 'desactivar'} este departamento?`)) {
            departamento.estado = nuevoEstado;
            this.saving = true;
            
            this.apiService.store('departamentosEmpresa/changeState/' + departamento.id, departamento).subscribe(response => { 
                this.saving = false;
                this.alertService.success('Departamento actualizado', `El departamento fue ${estadoTexto} exitosamente.`);
                this.filtrarDepartamento();
            }, error => {
                this.alertService.error(error);
                this.saving = false;
                // Revertir el cambio en caso de error
                departamento.estado = departamento.estado == '1' ? '0' : '1';
            });
        }
    }

    public editDepartamento(template: TemplateRef<any>, departamento: any) {
        this.departamento = { ...departamento }; // Crear una copia del departamento
        this.openModal(template);
    }

    public verAreas(departamento: any) {
        this.loadAreas(departamento.id);
        this.router.navigate(['/areas-empresa'], { queryParams: { id_departamento: departamento.id } });
        this.alertService.success('Áreas cargadas', `Se cargaron las áreas del departamento ${departamento.nombre}`);
    }

    public onSubmit() {
        if (!this.departamento.nombre || this.departamento.nombre.trim() === '') {
            this.alertService.error('El nombre del departamento es requerido');
            return;
        }

        if (!this.departamento.id_sucursal) {
            this.alertService.error('Debe seleccionar una sucursal');
            return;
        }

        this.saving = true;
        
        // Asegurar que activo tenga un valor por defecto
        if (!this.departamento.estado) {
            this.departamento.estado = '1';
        }

        const action = this.departamento.id ? 'actualizado' : 'creado';

        const urlRoute = this.departamento.id ? 'departamentosEmpresa/update' : 'departamentosEmpresa';
        
        this.apiService.store(urlRoute, this.departamento).subscribe(response => { 
            this.saving = false;
            this.alertService.success('Departamento guardado', `El departamento fue ${action} exitosamente.`);
            this.closeModal();
            this.filtrarDepartamento();
            this.resetDepartamento();
        }, error => {
            this.alertService.error(error);
            this.saving = false;
        });
    }

    public delete(id: number) {
        if (confirm('¿Está seguro de eliminar este departamento? Esta acción eliminará también todas las áreas asociadas y no se puede deshacer.')) {
            this.apiService.delete('departamentosEmpresa/', id).subscribe(data => {
                this.alertService.success('Departamento eliminado', 'El departamento fue eliminado exitosamente.');
                // Remover el elemento del array local
                if (this.departamentos && this.departamentos.data) {
                    this.departamentos.data = this.departamentos.data.filter((dept: any) => dept.id !== id);
                    this.departamentos.total--;
                }
            }, error => {
                this.alertService.error(error);
            });
        }
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    public descargar() {
        this.downloading = true;
        this.apiService.export('departamentosEmpresa/exportar', this.filtros).subscribe((data: Blob) => {
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

    public override openModal(template: TemplateRef<any>, config?: any) {
        this.loadSucursales();
        this.resetDepartamento();
        super.openModal(template, config);
    }

    public onSucursalChange() {
        // Limpiar áreas cuando cambie la sucursal si las tienes cargadas
        this.areas = [];
    }

    public onFilterSucursalChange() {
        // Filtrar automáticamente cuando cambie la sucursal en filtros
        this.filtrarDepartamento();
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

    private loadAreas(departamentoId: number) {
        this.apiService.getAll(`departamentosEmpresa/${departamentoId}/areas`).subscribe(areas => { 
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