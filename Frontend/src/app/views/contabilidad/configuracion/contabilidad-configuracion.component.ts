import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { GastosCategoriasComponent } from '../../compras/gastos/categorias/gastos-categorias.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';
import { TranslatePipe } from '@ngx-translate/core';

@Component({
    selector: 'app-contabilidad-configuracion',
    templateUrl: './contabilidad-configuracion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, GastosCategoriasComponent, TooltipModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ContabilidadConfiguracionComponent extends BaseComponent implements OnInit {

    public configuracion: any = {};
    public cuentas: any = {};
    public catalogo: any = [];
    public loading = false;
    public loadingCatalogo = false;
    public saving = false;
    public planillasModuloActivo = false;
    public esCostaRica = false;
    modalRef!: BsModalRef;

    readonly cuentasPlanillaGasto: { key: string; label: string }[] = [
        { key: 'id_cuenta_gasto_salarios', label: 'Gasto de salarios' },
        { key: 'id_cuenta_gasto_cargas_patronales', label: 'Gasto de cargas patronales' },
        { key: 'id_cuenta_gasto_aguinaldo', label: 'Gasto de provisión de aguinaldo' },
        { key: 'id_cuenta_gasto_vacaciones', label: 'Gasto de provisión de vacaciones' },
    ];

    readonly cuentasPlanillaPasivo: { key: string; label: string }[] = [
        { key: 'id_cuenta_pasivo_cargas_sociales', label: 'Cargas sociales por pagar' },
        { key: 'id_cuenta_pasivo_retencion_renta', label: 'Retención de renta por pagar' },
        { key: 'id_cuenta_pasivo_salarios_por_pagar', label: 'Salarios netos por pagar' },
        { key: 'id_cuenta_pasivo_provision_aguinaldo', label: 'Provisión de aguinaldo por pagar' },
        { key: 'id_cuenta_pasivo_provision_vacaciones', label: 'Provisión de vacaciones por pagar' },
    ];

    readonly cuentaPlanillaInsCr = {
        key: 'id_cuenta_pasivo_ins',
        label: 'INS riesgos del trabajo por pagar',
    };

    constructor( 
        public apiService: ApiService, 
        protected alertService: AlertService,
        private route: ActivatedRoute, 
        private router: Router,
        private modalService: BsModalService,
        private cdr: ChangeDetectorRef
    ) {
        super();
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.loadingCatalogo = true;
        this.loading = true;
        
        // Cargar catálogo y configuración en paralelo
        this.apiService.getAll('catalogo/list')
          .pipe(this.untilDestroyed())
          .subscribe(catalogo => {
            this.catalogo = catalogo;
            this.loadingCatalogo = false;
            this.cdr.markForCheck();
        }, error => {
            this.alertService.error(error);
            this.loadingCatalogo = false;
            this.cdr.markForCheck();
        });

        this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.apiService.read('contabilidad/configuracion/', this.apiService.auth_user().id_empresa)
          .pipe(this.untilDestroyed())
          .subscribe(response => {
            const envuelto = response && typeof response === 'object' && 'configuracion' in response;
            const data = envuelto ? response.configuracion : response;
            this.planillasModuloActivo = envuelto ? !!response.planillas_modulo_activo : false;
            this.esCostaRica = envuelto ? response.cod_pais_empresa === 'CR' : false;

            this.configuracion = data ?? {};
            if (!this.configuracion.id) {
                this.configuracion = { ...this.configuracion };
                this.configuracion.id_empresa = this.apiService.auth_user().id_empresa;
            }
            this.configuracion.separar_cuentas_iva = !!this.configuracion.separar_cuentas_iva;
            this.configuracion.abonos_en_cartera = !!this.configuracion.abonos_en_cartera;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    private validarCuentasPlanilla(): boolean {
        if (!this.planillasModuloActivo) {
            return true;
        }
        const requeridas = [
            ...this.cuentasPlanillaGasto,
            ...this.cuentasPlanillaPasivo,
            ...(this.esCostaRica ? [this.cuentaPlanillaInsCr] : []),
        ];
        const faltantes = requeridas.filter(
            (c) => this.configuracion[c.key] === null
                || this.configuracion[c.key] === undefined
                || this.configuracion[c.key] === ''
        );
        if (faltantes.length === 0) {
            return true;
        }
        this.alertService.error(
            'Complete las cuentas de planilla: ' + faltantes.map((c) => c.label).join(', ')
        );
        return false;
    }

    // Función trackBy para mejorar el rendimiento de ngFor
    trackByCuentaId(index: number, cuenta: any): any {
        return cuenta ? cuenta.id : index;
    }

        public onSubmit() {
            if (!this.validarCuentasPlanilla()) {
                return;
            }
            this.saving = true;
            this.apiService.store('contabilidad/configuracion', this.configuracion)
              .pipe(this.untilDestroyed())
              .subscribe(configuracion => {
                if (!this.configuracion.id) {
                    this.alertService.success('Configuracion creada', 'El configuracion fue añadido exitosamente.');
                }else{
                    this.alertService.success('Configuracion guardada', 'El configuracion fue guardado exitosamente.');
                }
                this.saving = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck(); });
        }


}
