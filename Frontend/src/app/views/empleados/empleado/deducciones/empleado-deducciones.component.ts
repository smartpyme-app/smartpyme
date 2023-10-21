import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-empleado-deducciones',
  templateUrl: './empleado-deducciones.component.html'
})
export class EmpleadoDeduccionesComponent implements OnInit {

	@Input() empleado:any = [];
    public deduccion:any = {};
    public listadeducciones:any = [];

 	public loading = false;

	modalRef!: BsModalRef;

   constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {
        this.apiService.getAll('deducciones').subscribe(listadeducciones => { 
            this.listadeducciones = listadeducciones;
            this.loading = false;
        }, error => {this.alertService.error(error); });
	}

    openModal(template: TemplateRef<any>) {
        if (!this.deduccion.id) {
                this.deduccion = {};
                this.deduccion.empleado_id = this.empleado.id;
        }
       this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
    }
    


    public onSelectDeduccion(deduccion_id:any){
        let d = this.listadeducciones.find((item:any) => item.id == deduccion_id);
        this.deduccion.deduccion_id = d.id;
        this.deduccion.total = d.total;
        this.deduccion.tipo = d.tipo;
    }

	public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('empleados/deduccion/', id) .subscribe(data => {
                for (let i = 0; i < this.empleado.deducciones.length; i++) { 
                    if (this.empleado.deducciones[i].id == data.id )
                        this.empleado.deducciones.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

	public onSubmit() {
	   this.loading = true;
	   this.deduccion.usuario_id = this.apiService.auth_user().id;
	   this.apiService.store('empleados/deduccion', this.deduccion).subscribe(deduccion => {
	      this.empleado.deducciones.push(deduccion);
          this.modalRef.hide();
          this.loading = false;
	   }, error => {this.alertService.error(error); this.loading = false; });


	}

}
