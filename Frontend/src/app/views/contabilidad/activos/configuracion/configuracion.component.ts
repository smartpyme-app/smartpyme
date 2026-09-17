import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';
import { ActivosNavTabsComponent } from '@views/contabilidad/activos/activos-nav-tabs/activos-nav-tabs.component';

@Component({
    selector: 'app-activos-configuracion',
    templateUrl: './configuracion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ActivosNavTabsComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ActivosConfiguracionComponent extends BaseComponent implements OnInit {

    public config: any = {};
    public loading = false;
    public saving = false;

    constructor(
        public apiService: ApiService,
        protected alertService: AlertService,
        private cdr: ChangeDetectorRef,
    ) {
        super();
    }

    ngOnInit() {
        this.cargar();
    }

    private cargar() {
        this.loading = true;
        this.apiService.getAll('activos/configuracion')
            .pipe(this.untilDestroyed())
            .subscribe(config => {
                this.config = config;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('activos/configuracion', this.config)
            .pipe(this.untilDestroyed())
            .subscribe(config => {
                this.config = config;
                this.saving = false;
                this.alertService.success('Guardado', 'Configuración de activos actualizada.');
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.saving = false;
                this.cdr.markForCheck();
            });
    }
}
