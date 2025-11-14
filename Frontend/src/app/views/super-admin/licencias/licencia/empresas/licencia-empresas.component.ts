import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

@Component({
    selector: 'app-licencia-empresas',
    templateUrl: './licencia-empresas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})
export class LicenciaEmpresasComponent extends BaseModalComponent implements OnInit {

    @Input() licencia: any = {};
    public empresas: any = [];
    public empresa: any = {};
    public buscador:string = '';
    public override loading:boolean = false;
    public override saving:boolean = false;

    constructor(
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private route: ActivatedRoute, private router: Router
    ){
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('empresas/list').subscribe(empresas => {
            this.empresas = empresas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }


    public override openModal(template: TemplateRef<any>, empresa:any) {
        this.empresa = empresa;
        this.empresa.id_empresa = '';
        super.openModal(template, {class: 'modal-md'});
    }

    // empresa
    public setEmpresa(empresa:any){
        if(!this.empresa.id_empresa){
            this.empresas.push(empresa);
        }
        this.empresa.id_empresa = empresa.id;
    }


    public onSubmit() {
        this.saving = true;
        this.empresa.id_licencia = this.licencia.id;
        this.apiService.store('licencia/empresa', this.empresa).subscribe(empresa => {
            if(!this.empresa.id)
                this.licencia.empresas.push(empresa);
            this.empresa = {};
            this.saving = false;
            if (this.modalRef) {
                this.closeModal();
            }
            this.alertService.success('Empresa agregado', 'El empresa fue agregado exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });
    }

    public delete(empresa:any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('licencia/empresa/', empresa.id) .subscribe(data => {
                for (let i = 0; i < this.licencia.empresas.length; i++) { 
                    if (this.licencia.empresas[i].id == data.id )
                        this.licencia.empresas.splice(i, 1);
                }
                this.alertService.success('Empresa eliminado', 'El empresa fue eliminado exitosamente.');
            }, error => {this.alertService.error(error); });
                   
        }

    }

}
