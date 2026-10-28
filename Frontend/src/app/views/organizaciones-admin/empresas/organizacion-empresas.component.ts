import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NgSelectModule } from '@ng-select/ng-select';
import { RouterModule } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';


@Component({
    selector: 'app-organizacion-empresas',
    templateUrl: './organizacion-empresas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, PopoverModule, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})

export class OrganizacionEmpresasComponent extends BaseCrudComponent<any> implements OnInit {

    public empresas:any = {};
    public empresasList:any = [];
    public empresa:any = {};

    constructor(
        public override apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'licencia/empresa',
            itemsProperty: 'empresas',
            itemProperty: 'empresa',
            reloadAfterSave: true,
            reloadAfterDelete: true,
            initNewItem: (item) => {
                const idLicencia = apiService.auth_user()?.empresa?.licencia?.id;
                if (idLicencia) {
                    item.id_licencia = idLicencia;
                }
                return item;
            },
            beforeSave: (item) => {
                if (!item.id_licencia) {
                    const idLicencia = apiService.auth_user()?.empresa?.licencia?.id;
                    if (idLicencia) {
                        item.id_licencia = idLicencia;
                    }
                }
                return item;
            },
            messages: {
                created: 'La empresa fue añadida exitosamente.',
                updated: 'La empresa fue guardada exitosamente.',
                deleted: 'La empresa fue quitada de la licencia.',
                createTitle: 'Empresa agregada',
                updateTitle: 'Empresa guardada',
                deleteTitle: 'Empresa quitada',
                deleteConfirm: '¿Quitar esta empresa de la licencia?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarEmpresas();
    }

    ngOnInit() {
        const idLicencia = this.idLicenciaSesion();
        if (idLicencia) {
            this.loadAll();
            return;
        }
        this.apiService.refreshEmpresaEnSesion()
            .pipe(this.untilDestroyed())
            .subscribe(() => this.loadAll());
    }

    private idLicenciaSesion(): number | undefined {
        return this.apiService.auth_user()?.empresa?.licencia?.id;
    }

    public override loadAll() {
        this.filtros.activo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        const idLicencia = this.idLicenciaSesion();
        if (!idLicencia) {
            this.loading = false;
            this.cdr.markForCheck();
            return;
        }
        this.filtros.id_licencia = idLicencia;
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarEmpresas();
    }

    public filtrarEmpresas(){
        this.apiService.getAll('licencias/empresas', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (empresas) => {
                    this.empresas = empresas;
                    this.loading = false;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                }
            });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.cdr.markForCheck();
        this.filtrarEmpresas();
    }

    /** No quitar la empresa titular de la licencia desde este listado. */
    esEmpresaTitularLicencia(fila: { id_empresa?: number }): boolean {
        const idTitular = this.apiService.auth_user()?.empresa?.licencia?.id_empresa;
        return idTitular != null && fila.id_empresa === idTitular;
    }

    public quitarDeLicencia(fila: { id: number }): void {
        super.delete(fila);
    }

    override openModal(template: TemplateRef<any>, empresa?: any) {
        this.apiService.getAll('licencias/empresas/select')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (empresasList) => {
                    this.empresasList = empresasList;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.cdr.markForCheck();
                }
            });

        // BaseCrud: 2º arg = item, 3º = config del modal (antes se pasaba mal y se borraba id_licencia).
        super.openModal(template, empresa, { class: 'modal-md', backdrop: 'static' });
    }
}
