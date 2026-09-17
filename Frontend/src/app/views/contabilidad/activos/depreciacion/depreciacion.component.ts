import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { BaseComponent } from '@shared/base/base.component';
import { ActivosNavTabsComponent } from '@views/contabilidad/activos/activos-nav-tabs/activos-nav-tabs.component';

@Component({
    selector: 'app-activos-depreciacion',
    templateUrl: './depreciacion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, CurrencyPipe, ActivosNavTabsComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ActivosDepreciacionComponent extends BaseComponent implements OnInit {

    public periodo = '';
    public preview: any = null;
    public loading = false;
    public ejecutando = false;

    constructor(
        public apiService: ApiService,
        protected alertService: AlertService,
        private cdr: ChangeDetectorRef,
    ) {
        super();
    }

    ngOnInit() {
        const now = new Date();
        this.periodo = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        this.cargarPreview();
    }

    cargarPreview() {
        if (!this.periodo) {
            return;
        }
        this.loading = true;
        this.apiService.getAll('activos/depreciacion/preview', { periodo: this.periodo })
            .pipe(this.untilDestroyed())
            .subscribe(preview => {
                this.preview = preview;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

    ejecutar() {
        if (!this.preview?.cantidad || this.preview?.ya_aplicada) {
            return;
        }
        if (!confirm(`¿Aplicar depreciación de ${this.periodo} por ${this.preview.total}?`)) {
            return;
        }
        this.ejecutando = true;
        this.apiService.store('activos/depreciacion/ejecutar', { periodo: this.periodo })
            .pipe(this.untilDestroyed())
            .subscribe(result => {
                this.alertService.success('Corrida aplicada', `Egreso #${result.id_egreso} creado.`);
                this.ejecutando = false;
                this.cargarPreview();
            }, error => {
                this.alertService.error(error);
                this.ejecutando = false;
                this.cdr.markForCheck();
            });
    }
}
