import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-cuentas-cobrar',
    templateUrl: './cuentas-cobrar.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    
})

export class CuentasCobrarComponent extends BasePaginatedComponent implements OnInit {

    public cobros: PaginatedResponse<any> = {} as PaginatedResponse;
    public buscador:any = '';

    constructor(apiService: ApiService, alertService: AlertService){
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.cobros;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.cobros = data;
    }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('cuentas-cobrar')
            .pipe(this.untilDestroyed())
            .subscribe(cobros => { 
                this.cobros = cobros;
                this.loading = false;
            }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.apiService.read('cuentas-cobrar/buscar/', this.buscador)
                .pipe(this.untilDestroyed())
                .subscribe(cobros => { 
                    this.cobros = cobros;
                }, error => {this.alertService.error(error); });
        }
    }

    public setEstado(venta:any, estado:string){
        venta.estado = estado;
        this.apiService.store('venta', venta)
            .pipe(this.untilDestroyed())
            .subscribe(venta => { 
                this.alertService.success('Venta actualizada', 'La venta fue actualizada exitosamente.');
            }, error => {this.alertService.error(error); });
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

}
