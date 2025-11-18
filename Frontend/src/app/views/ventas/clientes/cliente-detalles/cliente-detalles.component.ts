import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { TagInputModule } from 'ngx-chips';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

@Component({
    selector: 'app-cliente-detalles',
    templateUrl: './cliente-detalles.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TagInputModule],
    
})
export class ClienteDetallesComponent extends BaseModalComponent implements OnInit {

    public cliente: any = {};
    public camposPorTipoCliente: any = {
        'Persona': [
            'nombre', 'apellido', 'correo', 'dui',
            //  'tipo_contribuyente', 
            'nota', 'telefono', 'municipio', 'departamento', 'direccion'
        ],
        'Empresa': [
            'nombre_empresa', 'ncr', 'nit', 'giro', 'telefono',
            'municipio', 'departamento', 'empresa_direccion', 'nota'
            ,'tipo_contribuyente'
        ],
        'Extranjero': [
            'nombre', 'apellido', 'numero_identificacion', 'correo', 'pasaporte', 'pais',
            'nota', 'telefono', 'direccion','tipo_documento'
            ,'tipo_persona'
        ]
    };
    public override loading = false;
    public contacto: any = {};
    public tipoDocumento: any = {
        '13': 'DUI',
        '36': 'NIT',
        '03': 'Pasaporte',
        '02': 'Carnet de residente',
        '37': 'Otro'
    };
    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private route: ActivatedRoute, private router: Router
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.loadAll();
    }

    /**Carga todos los datos del cliente
     * @returns void*/
    public loadAll() {
        this.route.params.pipe(this.untilDestroyed()).subscribe((params: any) => {
            if (params.id) {
                this.loading = true;
                this.apiService.read('cliente/', params.id).pipe(this.untilDestroyed()).subscribe(cliente => {
                    this.cliente = cliente;
                    this.loading = false;
                }, error => { this.alertService.error(error); this.loading = false; });
            } else {
                this.cliente = {};
                this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
                this.cliente.id_usuario = this.apiService.auth_user().id;
            }
        });
    }

    /**Abre el modal para ver los contactos adicionales
     * @param template - TemplateRef del modal
     * @param contacto - Contacto a mostrar*/
    public override openModal(template: TemplateRef<any>, contacto: any) {
        this.contacto = contacto;
        super.openModal(template, {
            class: 'modal-lg',
            backdrop: 'static',
        });
    }

    /**Obtiene el tipo de cliente
     * @returns string - El tipo de cliente*/
    getTipoCliente() {
        return this.cliente.tipo;
    }

    /**Obtiene los campos válidos para el tipo de cliente actual
     * @returns string[] - Array con los nombres de campos válidos*/
    getVerificarInformacionTipo() {
        switch (this.cliente.tipo) {
            case 'Persona':
                return this.getCamposValidosPorTipo();
            case 'Empresa':
                return this.getCamposValidosPorTipo();
            case 'Extranjero':
                return this.getCamposValidosPorTipo();
            default:
                return '';
        }
    }

    /** Verifica si un campo específico debe mostrarse para el tipo de cliente actual
    * @param campo - Nombre del campo a verificar
    * @returns boolean - true si debe mostrarse, false si no */
    public mostrarCampo(campo: string): boolean {
        if (!this.cliente?.tipo) return false;

        const camposValidos = this.camposPorTipoCliente[this.cliente.tipo];
        return camposValidos ? camposValidos.includes(campo) : false;
    }

    /**Verifica si múltiples campos deben mostrarse
     * @param campos - Array de nombres de campos
     * @returns boolean - true si al menos uno debe mostrarse*/
    public mostrarAlgunCampo(campos: string[]): boolean {
        return campos.some(campo => this.mostrarCampo(campo));
    }

    /**Verifica si la sección de contactos adicionales debe mostrarse
     * @returns boolean - true si es tipo 'Empresa'*/
    public mostrarContactosAdicionales(): boolean {
        return this.cliente?.tipo === 'Empresa';
    }

    /**Obtiene la dirección correcta según el tipo de cliente
     * @returns string - La dirección correspondiente*/
    public obtenerDireccion(): string {
        if (this.cliente?.tipo === 'Empresa') {
            return this.cliente.empresa_direccion || '';
        }
        return this.cliente?.direccion || '';
    }

    /**Obtiene todos los campos válidos para el tipo actual
     * @returns string[] - Array con los nombres de campos válidos*/
    public getCamposValidosPorTipo(): string[] {
        return this.camposPorTipoCliente[this.cliente?.tipo] || [];
    }

    /**Verifica si el cliente tiene un tipo válido configurado
     * @returns boolean - true si el tipo está configurado*/
    public tieneConfiguracionValida(): boolean {
        return !!(this.cliente?.tipo && this.camposPorTipoCliente[this.cliente.tipo]);
    }

    /**Obtiene el nombre del tipo de documento
     * @returns string - El nombre del tipo de documento*/
    public obtenerNombreTipoDocumento(): string {
        return this.tipoDocumento[this.cliente.tipo_documento] || '';
    }

}