import { Component, OnInit, TemplateRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { BancosComponent } from '../bancos/bancos.component';

@Component({
    selector: 'app-formas-de-pago',
    templateUrl: './formas-de-pago.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, BancosComponent],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class FormasDePagoComponent extends BaseCrudComponent<any> implements OnInit {

    public formas_pago:any = [];
    public forma_pago:any = {};
    public empresa:any = {};
    public bancos:any = [];
    public wompiActivo:boolean = false;
    public contabilidadHabilitada: boolean = false;
    private guardandoFormaPago: boolean = false; // Protección contra múltiples llamadas

    private cdr = inject(ChangeDetectorRef);

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private funcionalidadesService: FuncionalidadesService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'forma-de-pago',
            itemsProperty: 'formas_pago',
            itemProperty: 'forma_pago',
            messages: {
                created: 'Las formas de pago fueron actualizadas exitosamente.',
                updated: 'Las formas de pago fueron actualizadas exitosamente.',
                createTitle: 'Formas de pago actualizadas',
                updateTitle: 'Formas de pago actualizadas'
            },
            beforeSave: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                return item;
            }
        });
    }

    ngOnInit() {
        this.empresa = this.apiService.auth_user().empresa;
        this.verificarAccesoContabilidad(); // loadAll se llama dentro de verificarAccesoContabilidad
    }

    verificarAccesoContabilidad() {
        this.funcionalidadesService.verificarAcceso('contabilidad')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (acceso) => {
                    this.contabilidadHabilitada = acceso;
                    this.loadAll(); // Cargar formas de pago después de verificar contabilidad
                    this.cargarBancos(); // Cargar bancos después de verificar contabilidad
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    console.error('Error al verificar acceso a contabilidad:', error);
                    this.contabilidadHabilitada = false;
                    this.loadAll(); // Cargar formas de pago incluso si hay error
                    this.cargarBancos(); // Cargar bancos incluso si hay error
                    this.cdr.markForCheck();
                }
            });
    }

    cargarBancos() {
        // Solo cargar bancos si tiene contabilidad habilitada (para el select del modal)
        // Si no tiene contabilidad, los bancos se muestran en el componente <app-bancos> y no se necesitan para el modal
        if (this.contabilidadHabilitada) {
            this.apiService.getAll('banco/cuentas/list')
                .pipe(this.untilDestroyed())
                .subscribe(bancos => {
                    this.bancos = bancos;
                    this.loading = false;
                    this.cdr.markForCheck();
                }, error => {
                    this.alertService.error(error);
                    this.cdr.markForCheck();
                });
        }
        // Si no tiene contabilidad, no cargar bancos aquí ya que se muestran en <app-bancos>
    }

    public override loadAll(): Promise<void> {
        return new Promise((resolve, reject) => {
            this.loading = true;
            this.apiService.getAll('formas-de-pago')
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (formas_pago) => {
                if (!this.contabilidadHabilitada) {
                    // LÓGICA DEL BRANCH DE MAIN: Mostrar todas las formas de pago posibles
                    // independientemente de si existen en BD o no
                    const todasLasFormas = [
                        'Efectivo',
                        'Transferencia',
                        'Tarjeta de crédito/débito',
                        'Cheque',
                        'Contra entrega',
                        'Wompi',
                        'Paypal',
                        'Bitcoin',
                        'Compra click',
                        'N1co',
                        'Otro'
                    ];

                    // Crear un array con todas las formas de pago
                    // En el branch de main, el estado activo se determina por la existencia del registro en BD
                    // Si existe en BD = activo, si no existe = inactivo
                    this.formas_pago = todasLasFormas.map((nombre: string) => {
                        const formaExistente = formas_pago.find((fp: any) => fp.nombre === nombre);
                        // Si existe en BD, está activa (no hay campo activo en la tabla)
                        const estaActivo = !!formaExistente;
                        return {
                            nombre: nombre,
                            activo: estaActivo,
                            id: formaExistente ? formaExistente.id : null,
                            id_banco: formaExistente ? formaExistente.id_banco : null,
                            banco: formaExistente ? formaExistente.banco : null
                        };
                    });
                } else {
                    // LÓGICA CON CONTABILIDAD: Mostrar solo las formas de pago que existen en BD
                    this.formas_pago = formas_pago;
                }

                        this.wompiActivo = this.formas_pago.find((item:any) => item.nombre == 'Wompi')?.activo || false;
                        this.loading = false;
                        this.cdr.markForCheck();
                        resolve();
                    },
                    error: (error) => {
                        this.alertService.error(error);
                        this.loading = false;
                        this.cdr.markForCheck();
                        reject(error);
                    }
                });
        });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    public override openModal(template: TemplateRef<any>, forma_pago?: any){
        super.openModal(template, forma_pago);
    }

    public async toggleFormaPago(forma: any): Promise<void> {
        // LÓGICA DEL BRANCH DE MAIN: Método para activar/desactivar formas de pago
        // Este método solo se usa cuando NO tiene contabilidad habilitada
        // En el branch de main, el estado activo se maneja creando/eliminando registros,
        // NO usando un campo activo en la tabla

        // Protección contra múltiples llamadas simultáneas
        if (this.guardandoFormaPago) {
            return;
        }

        // Guardar el estado anterior para comparar
        const estadoAnterior = forma.activo;
        const teniaId = !!forma.id;
        const nuevoEstado = forma.activo; // El estado ya fue cambiado por el ngModel

        this.guardandoFormaPago = true;

        try {
            if (nuevoEstado && !teniaId) {
                // Si se está activando y no existe, crear el registro
                const formaToSave: any = {
                    nombre: forma.nombre,
                    id_empresa: this.apiService.auth_user().id_empresa
                };
                await super.onSubmit(formaToSave, true);
            } else if (!nuevoEstado && teniaId) {
                // Si se está desactivando y existe, eliminar el registro
                await this.apiService.delete('forma-de-pago/', forma.id).pipe(this.untilDestroyed()).toPromise();
            } else if (nuevoEstado && teniaId) {
                // Si ya existe y se está activando (ya está activo), no hacer nada
                // Esto puede pasar si el usuario hace doble click
            }

            // Después de guardar/eliminar, recargar para obtener el estado actualizado desde el servidor
            await this.loadAll();
        } catch (error) {
            // Si hay error, revertir el cambio en el UI
            forma.activo = estadoAnterior;
            this.cdr.markForCheck();
            throw error;
        } finally {
            this.guardandoFormaPago = false;
        }
    }

    public onSubmitWompi() {
        this.saving = true;
        this.apiService.store('wompi', this.empresa).subscribe(
            () => {
                this.saving = false;
                this.alertService.success('Conexión exitosa', 'Conexión con Wompi exitosa, ya puede crear enlaces de pago para tus ventas.');
            },
            (error) => {
                this.alertService.error(error);
                this.saving = false;
            }
        );
    }
}
