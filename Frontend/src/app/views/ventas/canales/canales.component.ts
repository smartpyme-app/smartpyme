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
    selector: 'app-canales',
    templateUrl: './canales.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, FilterPipe, PaginationComponent, PopoverModule, TooltipModule],

})

export class CanalesComponent extends BaseModalComponent implements OnInit {

    public canales:any = [];
    public canal:any = {};
    public override loading:boolean = false;
    public filtro:any = {};
    public filtrado:boolean = false;

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
        this.apiService.getAll('canales').subscribe(canales => { 
            this.canales = canales;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public override openModal(template: TemplateRef<any>, canal:any) {
        this.canal = canal;
        if (!this.canal.id) {
            this.canal.id_empresa = this.apiService.auth_user().id_empresa;
            this.canal.enable = true;
        }
        super.openModal(template, {class: 'modal-md', backdrop: 'static'});
    }

    public setEstado(canal:any){
        this.canal = canal;
        this.onSubmit(true);
    }

    public onSubmit(isStatusChange: boolean = false) {
        this.loading = true;
        this.apiService.store('canal', this.canal).subscribe(canal => {
            if (isStatusChange) {
                const index = this.canales.findIndex((c: any) => c.id === canal.id);
                if (index !== -1) {
                    this.canales[index] = canal;
                }
                this.alertService.success('Estado actualizado', 'El estado del canal fue cambiado exitosamente.');
            } else {
                if (!this.canal.id) {
                    this.canales.push(canal);
                }
                
                if (this.modalRef) {
                    this.closeModal();
                }
                
                setTimeout(() => {
                    if (!this.canal.id) {
                        this.alertService.success('Canal creado', 'El canal fue añadido exitosamente.');
                    } else {
                        this.alertService.success('Canal guardado', 'El canal fue guardado exitosamente.');
                    }
                }, 300);
            }
            this.loading = false;
        }, error => {
            this.alertService.error(error); 
            this.loading = false;
        });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('gasto/', id) .subscribe(data => {
                for (let i = 0; i < this.canales.length; i++) { 
                    if (this.canales[i].id == data.id )
                        this.canales.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
