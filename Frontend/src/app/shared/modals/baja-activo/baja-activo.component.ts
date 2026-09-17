import { Component, OnInit, TemplateRef, ViewChild, Output, EventEmitter, ChangeDetectorRef, inject } from '@angular/core';

import { CommonModule } from '@angular/common';

import { FormsModule } from '@angular/forms';

import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';

import { ApiService } from '@services/api.service';

import { ModalManagerService } from '@services/modal-manager.service';

import { BaseModalComponent } from '@shared/base/base-modal.component';



@Component({

    selector: 'app-baja-activo',

    templateUrl: './baja-activo.component.html',

    standalone: true,

    imports: [CommonModule, FormsModule, NgSelectModule],

})

export class BajaActivoComponent extends BaseModalComponent implements OnInit {



    @Output() update = new EventEmitter<any>();



    @ViewChild('mBaja') mBaja!: TemplateRef<any>;



    public activo: any = {};

    public bajaForm: any = {};

    public sucursales: any[] = [];

    public usuarios: any[] = [];



    private cdr = inject(ChangeDetectorRef);



    constructor(

        public apiService: ApiService,

        protected override modalManager: ModalManagerService,

        protected override alertService: AlertService,

    ) {

        super(modalManager, alertService);

    }



    ngOnInit() {

        this.apiService.getAll('sucursales/list')

            .pipe(this.untilDestroyed())

            .subscribe(sucursales => {

                this.sucursales = sucursales;

                this.cdr.markForCheck();

            }, error => this.alertService.error(error));



        this.apiService.getAll('usuarios/list')

            .pipe(this.untilDestroyed())

            .subscribe(usuarios => {

                this.usuarios = usuarios;

                this.cdr.markForCheck();

            }, error => this.alertService.error(error));

    }



    public open(activo: any) {

        if (!activo?.id || activo.estado_registro === 'baja') {

            return;

        }

        this.activo = activo;

        this.initBajaForm();

        this.openModal(this.mBaja, { class: 'modal-md' });

    }



    private initBajaForm() {

        this.bajaForm = {

            tipo: 'desecho',

            fecha: this.apiService.date(),

            motivo: '',

            monto_venta: null,

            id_sucursal: null,

            id_responsable: null,

            ubicacion: '',

        };

    }



    public registrarBaja() {

        const labels: Record<string, string> = {

            desecho: 'desecho',

            venta: 'venta',

            transferencia: 'transferencia',

        };

        if (!confirm(`¿Confirmar ${labels[this.bajaForm.tipo] || 'operación'} del activo?`)) {

            return;

        }

        this.loading = true;

        this.apiService.store(`activo/${this.activo.id}/baja`, this.bajaForm)

            .pipe(this.untilDestroyed())

            .subscribe(activo => {

                this.loading = false;

                this.closeModal();

                this.update.emit(activo);

                this.alertService.success('Registrado', 'Operación aplicada correctamente.');

                this.cdr.markForCheck();

            }, error => {

                this.alertService.error(error);

                this.loading = false;

                this.cdr.markForCheck();

            });

    }

}

