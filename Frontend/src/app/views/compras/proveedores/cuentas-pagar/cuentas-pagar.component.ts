import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
    selector: 'app-cuentas-pagar',
    templateUrl: './cuentas-pagar.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class CuentasPagarComponent extends BasePaginatedComponent implements OnInit {

	public pagos: PaginatedResponse<any> = {} as PaginatedResponse;
    public buscador:any = '';

    constructor(apiService: ApiService, alertService: AlertService, private cdr: ChangeDetectorRef){
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.pagos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.pagos = data;
    }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('cuentas-pagar')
            .pipe(this.untilDestroyed())
            .subscribe(pagos => { 
                this.pagos = pagos;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.apiService.read('cuentas-pagar/buscar/', this.buscador)
                .pipe(this.untilDestroyed())
                .subscribe(pagos => { 
                    this.pagos = pagos;
                    this.cdr.markForCheck();
                }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
        }
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

}
