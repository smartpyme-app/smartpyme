import { Component, OnInit, Input, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ModalModule } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { SharedDataService } from '@services/shared-data.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { FilterPipe } from '@pipes/filter.pipe';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';

@Component({
    selector: 'app-admin-usuarios',
    templateUrl: './admin-usuarios.component.html',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        FormsModule,
        NgSelectModule,
        TooltipModule,
        ModalModule,
        FilterPipe,
        NotificacionesContainerComponent,
        PaginationComponent,
    ],

})

export class AdminUsuariosComponent extends BaseCrudComponent<any> implements OnInit {

    public usuario:any = {};
    public sucursales:any = [];
    public bodegas:any = [];
    public sucursalesList:any = [];
    public empresas:any = [];
    public usuarios:any = {};
    public roles:any = [];
    public paginacion = [];
    public filtrado:boolean = false;
    public showpassword:boolean = false;
    public showpassword2:boolean = false;
    public downloading:boolean = false;

    constructor(
        apiService:ApiService,
        alertService:AlertService,
        modalManager: ModalManagerService,
        private sharedDataService: SharedDataService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'admin-usuario',
            itemsProperty: 'usuarios',
            itemProperty: 'usuario',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El usuario fue añadido exitosamente.',
                updated: 'El usuario fue guardado exitosamente.',
                createTitle: 'Usuario creado',
                updateTitle: 'Usuario guardado'
            },
            afterSave: () => {
                this.loadAll();
            }
        });
    }

    protected aplicarFiltros(): void {
        this.onFiltrar();
    }

	ngOnInit() {
        this.filtros.id_empresa = '';
        this.filtros.id_sucursal = '';
        this.filtros.tipo = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loadAll();

        this.apiService.getAll('empresas/list')
            .pipe(this.untilDestroyed())
            .subscribe(empresas => {
                this.empresas = empresas;
            }, error => {this.alertService.error(error); });

        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (sucursales) => {
                    this.sucursalesList = sucursales;
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });
    }

    public override loadAll(closeFilterModal = false){
        this.loading = true;
        this.apiService.getAll('admin-usuarios', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => {
                this.usuarios = usuarios;
                this.usuarios.data.forEach((usuario:any) => {
                    const rol = usuario.roles?.[0];
                    usuario.rol_name = rol?.name ?? '';
                    usuario.rol_id = rol?.id ?? usuario.rol_id;
                });
                this.loading = false;
                if (closeFilterModal) {
                  this.modalRef?.hide();
                }
            }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('roles')
            .pipe(this.untilDestroyed())
            .subscribe(roles => {
                this.roles = roles;
                this.roles.forEach((rol:any) => {
                    rol.name = rol.name.split('_')
                                     .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
                                     .join(' ');
                });
            }, error => {this.alertService.error(error); });
    }

  openFilterModal(template: TemplateRef<any>) {
    this.modalRef = this.modalManager.openModal(template, { class: 'modal-lg' });
  }

    override openModal(template: TemplateRef<any>, usuario?: any) {
        // Copia superficial para no mutar la fila de la tabla al editar.
        this.usuario = usuario ? { ...usuario } : {};

        if (!this.usuario.id) {
            this.usuario.rol_id = 2;
        }

        const afterSucursales = () => {
            this.setSucursales();
        };

        if (this.sucursalesList?.length) {
            afterSucursales();
        } else {
            this.apiService.getAll('sucursales/list')
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (sucursales) => {
                        this.sucursalesList = sucursales;
                        afterSucursales();
                    },
                    error: (error) => {
                        this.alertService.error(error);
                    }
                });
        }

        // Una sola fuente / caché compartida; evita tormentas de GET si el modal o varios suscriptores piden la lista varias veces.
        this.sharedDataService.getBodegas()
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (bodegas) => {
                    this.bodegas = bodegas;
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });

        super.openLargeModal(template);
    }

    setSucursales(): void {
        this.sucursales = this.sucursalesList.filter((item: any) => item.id_empresa == this.usuario.id_empresa);
        const current = this.usuario.id_sucursal;
        const stillValid = this.sucursales.some((s: any) => String(s.id) === String(current));
        if (!stillValid && this.sucursales.length) {
            this.usuario.id_sucursal = this.sucursales[0].id;
        }
    }

    selectSucursal(){
        this.usuario.id_bodega = this.bodegas[0]?.id;
    }

    public descargarUsuarios(): void {
        this.downloading = true;
        const params = { ...this.filtros };
        delete params.paginate;
        this.apiService.export('admin-usuarios/exportar', params).subscribe((data: Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'usuarios-smartpyme.xlsx';
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

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }

    public mostrarPassword2(){
        this.showpassword2 = !this.showpassword2;
    }

    public override async onSubmit(item?: any, isStatusChange: boolean = false): Promise<void> {
        await super.onSubmit(item, isStatusChange);
    }

    public setEstado(usuario:any){
        this.apiService.store('admin-usuario', usuario)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (usuario) => {
                    if(usuario.enable == 1){
                        this.alertService.success('Usuario activado', 'El usuario fue activado exitosamente.');
                    }else{
                        this.alertService.success('Usuario desactivado', 'El usuario fue desactivado exitosamente.');
                    }
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;

        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        this.apiService.delete('admin-usuario/', itemToDelete)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (deletedItem: any) => {
                    const index = this.usuarios.data?.findIndex((u: any) => u.id === deletedItem.id);
                    if (index !== -1 && index >= 0) {
                        this.usuarios.data.splice(index, 1);
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

    onFiltrar(){
        this.loading = true;
        this.apiService.store('admin-usuarios/filtrar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => {
                this.usuarios = usuarios;
                this.loading = false;
                this.closeModal();
            }, error => {this.alertService.error(error); this.loading = false;});
    }

}
