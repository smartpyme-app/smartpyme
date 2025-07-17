import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

interface ImportResponse {
    success: boolean;
    message: string;
    filas_procesadas?: number;
    empresa_id?: number;
    error?: string;
    errores?: any[];
    errores_adicionales?: string[];
}

interface ValidationError {
    fila: number;
    columna: string;
    errores: string[];
    valores: any;
}

@Component({
    selector: 'app-importar-excel',
    templateUrl: './importar-excel.component.html'
})
export class ImportarExcelComponent implements OnInit {

    @Input() tipo: string = 'button';
    @Input() nombre: string = '';
    @Output() loadAll = new EventEmitter();

    public loading: boolean = false;
    public file: any = {};
    public importResult: any = null;
    public showResults: boolean = false;
    public validationErrors: ValidationError[] = [];
    public businessErrors: string[] = [];

    modalRef!: BsModalRef;

    constructor(
        private apiService: ApiService,
        public alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.resetState();
    }

    openModal(template: TemplateRef<any>) {
        this.resetState();
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
    }

    setFile(event: any) {
        this.file.file = event.target.files[0];
        this.resetState();
    }

    onSubmit(event: any) {
        if (!this.file.file) {
            this.alertService.error('Por favor seleccione un archivo');
            return;
        }

        console.log('Iniciando importación:', this.file);

        let formData: FormData = new FormData();
        for (var key in this.file) {
            formData.append(key, this.file[key]);
        }

        this.loading = true;
        this.resetState();

        this.apiService.store(this.nombre.toLowerCase() + '/importar', formData).subscribe(
            (response: ImportResponse) => {
                this.loading = false;
                this.handleSuccessResponse(response);
            },
            (error: any) => {
                this.loading = false;
                this.handleErrorResponse(error);
            }
        );
    }

    private handleSuccessResponse(response: ImportResponse) {
        this.importResult = response;
        this.showResults = true;

        if (response.success) {
            const filasText = response.filas_procesadas === 1 ? 'fila procesada' : 'filas procesadas';
            this.alertService.success(
                'Importación exitosa',
                `${response.message}. ${response.filas_procesadas} ${filasText}`
            );

            // Auto-cerrar modal después de mostrar éxito
            setTimeout(() => {
                this.closeModalAndReload();
            }, 2000);
        }
    }

    private handleErrorResponse(errorResponse: any) {
        console.error('Error en importación:', errorResponse);

        // Intentar parsear la respuesta de error
        const error = errorResponse.error || errorResponse;
        this.importResult = error;
        this.showResults = true;

        if (error.errores && Array.isArray(error.errores)) {
            // Errores de validación de Laravel Excel
            this.validationErrors = error.errores;
            this.alertService.error(
                `Errores de validación: Se encontraron ${error.errores.length} errores en el archivo. Revise los detalles.`
            );
        } else if (error.error && typeof error.error === 'string') {
            // Errores de negocio (duplicados, cuenta padre no encontrada, etc.)
            this.businessErrors = [error.error];
            if (error.errores_adicionales && error.errores_adicionales.length > 0) {
                this.businessErrors = [...this.businessErrors, ...error.errores_adicionales];
            }
            this.alertService.error(`Error en importación: ${error.error}`);
        } else {
            // Error genérico
            this.alertService.error(`Error en importación: ${error.message || 'Error desconocido'}`);
        }
    }

    private resetState() {
        this.importResult = null;
        this.showResults = false;
        this.validationErrors = [];
        this.businessErrors = [];
    }

    private closeModalAndReload() {
        this.modalRef.hide();
        this.loadAll.emit();
        this.alertService.modal = false;
        this.resetState();
    }

    public tryAgain() {
        this.resetState();
    }

    public downloadTemplate() {
        const url = `${this.apiService.baseUrl}/docs/${this.nombre.toLowerCase()}-format.xlsx`;
        window.open(url, '_blank');
    }

    public closeModal() {
        this.modalRef.hide();
        this.alertService.modal = false;
        this.resetState();
    }
}
