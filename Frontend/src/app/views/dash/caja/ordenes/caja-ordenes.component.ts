import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';
import { ModalManagerService } from '../../../../services/modal-manager.service';
import { BaseModalComponent } from '../../../../shared/base/base-modal.component';

@Component({
    selector: 'app-caja-ordenes',
    templateUrl: './caja-ordenes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class CajaOrdenesComponent extends BaseModalComponent implements OnInit {

    public ordenes:any = [];
    public orden:any = {};
    public override loading:boolean = false;
    public filterBy:any[] = ['nombre_usuario', 'nombre_cliente', 'id'];
    public ordenesResfresh:any;

    constructor( 
          private apiService: ApiService,
          protected override alertService: AlertService,
          protected override modalManager: ModalManagerService,
          private route: ActivatedRoute, private router: Router
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.loading = true;
        this.apiService.read('dash/cajero/', this.apiService.auth_user().id).subscribe(ordenes => { 
            this.ordenes = ordenes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
        
        this.ordenesResfresh = setInterval(()=> {
            if (!this.loading)
                this.loadAll();
        }, 25000);  
    }


    public loadAll() {
        // this.loading = true;
        this.apiService.read('dash/cajero/', this.apiService.auth_user().id).subscribe(ordenes => { 
            this.ordenes = ordenes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public override openModal(template: TemplateRef<any>, orden:any) {
        this.orden = orden;
        super.openModal(template);
    }


    public setEstado(orden:any, estado:any){
        if (estado == 'Entregada') {
            if (confirm("¿Confirma que la orden esta lista?")){
                this.orden = orden;
                this.orden.estado = estado;
                for (var i = 0; i < this.orden.detalles.length; ++i) {
                    this.setEstadoDetalle(this.orden.detalles[i], estado);
                }
                this.onSubmit();
            }
        }else{
            this.orden = orden;
            this.orden.estado = estado;
            for (var i = 0; i < this.orden.detalles.length; ++i) {
                this.setEstadoDetalle(this.orden.detalles[i], estado);
            }
            this.onSubmit();
        }
    }

    public onSubmit() {
          this.loading = true;
          // Guardamos la orden
          this.apiService.store('orden', this.orden).subscribe(orden => {
                if (orden.estado == 'Entregada') {
                    this.orden = {};
                }
                // this.alertService.success("Datos guardados");
                this.loading = false;
          },error => {this.alertService.error(error); this.loading = false; });
    }

    public setEstadoDetalle(detalle:any, estado:string) {
          this.loading = true;
          detalle.estado = estado;
          this.apiService.store('orden/detalle', detalle).subscribe(detalle => {
                detalle = detalle;
                // this.alertService.success("Datos guardados");
                this.loading = false;
          },error => {this.alertService.error(error); this.loading = false; });
    }

    ngOnDestroy(){
        clearInterval(this.ordenesResfresh);

    }

    public imprimirDoc(orden:any){
        window.open(this.apiService.baseUrl + '/api/orden/impresion/' + orden.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

}
