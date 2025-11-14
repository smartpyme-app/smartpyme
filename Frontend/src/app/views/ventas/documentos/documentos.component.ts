import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { FilterPipe } from '@pipes/filter.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';


@Component({
    selector: 'app-documentos',
    templateUrl: './documentos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, FilterPipe, PaginationComponent, PopoverModule, TooltipModule],
    
})

export class DocumentosComponent extends BaseModalComponent implements OnInit {

    public documentos:any = [];
    public documento:any = {};
    public sucursales:any = [];
    public override loading:boolean = false;
    public filtro:any = {};
    public filtrado:boolean = false;

    public nuevaResolucion:boolean = false;
    public change:boolean = false;

    constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ){
        super(modalManager, alertService);
    }

    ngOnInit() {

        this.loadAll();
    }

    public loadAll() {        
        this.loading = true;
        this.filtro.estado = '';
        this.apiService.getAll('documentos').subscribe(documentos => { 
            this.documentos = documentos;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public override openModal(template: TemplateRef<any>, documento?: any, nuevaResolucion?: boolean): void;
    public override openModal(template: TemplateRef<any>, config?: any): void;
    public override openModal(template: TemplateRef<any>, documentoOrConfig?: any, nuevaResolucion?: boolean): void {
        // Si el segundo parámetro es un objeto con propiedades de documento o es undefined/null
        // y el tercer parámetro es un boolean, entonces es la firma personalizada
        const isCustomSignature = nuevaResolucion !== undefined || 
            (documentoOrConfig && typeof documentoOrConfig === 'object' && !documentoOrConfig.class && !documentoOrConfig.size && !documentoOrConfig.backdrop);
        
        if (isCustomSignature) {
            const documento = documentoOrConfig || {};
            this.documento = documento;
            this.nuevaResolucion = nuevaResolucion || false;
            
            if (!this.documento.id) {
                this.documento.id_empresa = this.apiService.auth_user().id_empresa;
                this.documento.id_sucursal = this.apiService.auth_user().id_sucursal;
                this.documento.activo = true;
                this.documento.correlativo = 1;
            }
            this.apiService.getAll('sucursales/list').subscribe(sucursales => {
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error);});
            super.openModal(template, {class: 'modal-md', backdrop: 'static'});
        
            if (nuevaResolucion) {
                this.documento.correlativo = '';
                this.documento.rangos = '';
                this.documento.numero_autorizacion = '';
                this.documento.resolucion = '';
                this.documento.fecha = '';
                this.documento.activo = true;
                this.documento.nota = '';
            }
        } else {
            // Es la firma base, solo pasar la config
            super.openModal(template, documentoOrConfig);
        }
    }

    public setEstado(documento:any,change:boolean = false){
        this.documento = documento;
        this.documento.change = change;
        this.onSubmit();
    }

    public onSubmit(){
        this.loading = true;
        this.documento.nuevaResolucion = this.nuevaResolucion;
        this.apiService.store('documento', this.documento).subscribe(documento => {
            if (!this.documento.id) {
                this.documentos.push(documento);
                this.alertService.success('Documento creado', 'El documento fue añadido exitosamente.');
            }else{
                this.alertService.success('Documento guardado', 'El documento fue guardado exitosamente.');
            }
            this.loading = false;
            this.loadAll();
            if (this.modalRef) {
                this.closeModal();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('gasto/', id) .subscribe(data => {
                for (let i = 0; i < this.documentos.length; i++) { 
                    if (this.documentos[i].id == data.id )
                        this.documentos.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
