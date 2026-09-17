import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, ActivatedRoute } from '@angular/router';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';

@Component({
    selector: 'app-capitalizar-compra',
    templateUrl: './capitalizar-compra.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, CurrencyPipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CapitalizarCompraComponent extends BaseComponent implements OnInit {

    public compraId = 0;
    public lineas: any[] = [];
    public loading = false;

    constructor(
        public apiService: ApiService,
        protected alertService: AlertService,
        private route: ActivatedRoute,
        private cdr: ChangeDetectorRef,
    ) {
        super();
    }

    ngOnInit() {
        this.route.params.pipe(this.untilDestroyed()).subscribe((params: any) => {
            this.compraId = Number(params.id);
            this.cargarPendientes();
        });
    }

    private cargarPendientes() {
        this.loading = true;
        this.apiService.read('activos/pendientes-compra/', this.compraId)
            .pipe(this.untilDestroyed())
            .subscribe(lineas => {
                this.lineas = lineas ?? [];
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }
}
