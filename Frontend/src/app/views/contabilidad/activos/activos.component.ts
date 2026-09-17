import { Component, OnInit, TemplateRef, ViewChild, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';
import { BajaActivoComponent } from '@shared/modals/baja-activo/baja-activo.component';
import { ActivosNavTabsComponent } from '@views/contabilidad/activos/activos-nav-tabs/activos-nav-tabs.component';

@Component({
    selector: 'app-activos',
    templateUrl: './activos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TooltipModule, PopoverModule, CurrencyPipe, BajaActivoComponent, ActivosNavTabsComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ActivosComponent extends BasePaginatedComponent implements OnInit {

    @ViewChild('bajaActivo') bajaActivo!: BajaActivoComponent;

    public activos: PaginatedResponse<any> = {} as PaginatedResponse;
    public categorias: any[] = [];
    public sucursales: any[] = [];
    public override filtros: any = {};
    modalRef!: BsModalRef;

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        private modalService: BsModalService,
        private cdr: ChangeDetectorRef,
    ) {
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.activos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.activos = data;
    }

    ngOnInit() {
        this.apiService.getAll('activos/categorias', { list: 1 })
            .pipe(this.untilDestroyed())
            .subscribe(categorias => {
                this.categorias = categorias;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
                this.sucursales = sucursales;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); this.cdr.markForCheck(); });

        this.loadAll();
    }

    public loadAll() {
        this.filtros.buscador = '';
        this.filtros.id_categoria = '';
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.orden = 'fecha_compra';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarActivos();
    }

    public filtrarActivos() {
        this.loading = true;
        this.apiService.getAll('activos', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(activos => {
                this.activos = activos;
                this.loading = false;
                this.modalRef?.hide();
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

    public openFilter(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template, { class: 'modal-md' });
    }

    public onBajaAplicada() {
        this.filtrarActivos();
    }

    public onDelete(activo: any) {
        if (!confirm('¿Eliminar este activo fijo?')) {
            return;
        }
        this.apiService.delete('activo/', activo.id)
            .pipe(this.untilDestroyed())
            .subscribe(() => {
                this.alertService.success('Eliminado', 'Activo eliminado correctamente.');
                this.filtrarActivos();
            }, error => this.alertService.error(error));
    }
}
