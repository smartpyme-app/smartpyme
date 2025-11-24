import { Component, OnInit, TemplateRef, Input, Output, EventEmitter, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
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
        this.apiService.read('dash/cajero/', this.apiService.auth_user().id)
          .pipe(this.untilDestroyed())
          .subscribe(ordenes => { 
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
        this.apiService.read('dash/cajero/', this.apiService.auth_user().id)
          .pipe(this.untilDestroyed())
          .subscribe(ordenes => { 
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

    public async onSubmit() {
          this.loading = true;
          try {
              // Guardamos la orden
              const ordenGuardada = await this.apiService.store('orden', this.orden)
                  .pipe(this.untilDestroyed())
                  .toPromise();
              
              if (ordenGuardada.estado == 'Entregada') {
                  this.orden = {};
              }
              // this.alertService.success("Datos guardados");
          } catch (error: any) {
              this.alertService.error(error);
          } finally {
              this.loading = false;
          }
    }

    public async setEstadoDetalle(detalle:any, estado:string) {
          this.loading = true;
          detalle.estado = estado;
          try {
              const detalleGuardado = await this.apiService.store('orden/detalle', detalle)
                  .pipe(this.untilDestroyed())
                  .toPromise();
              
              // this.alertService.success("Datos guardados");
          } catch (error: any) {
              this.alertService.error(error);
          } finally {
              this.loading = false;
          }
    }

    ngOnDestroy(){
        clearInterval(this.ordenesResfresh);

    }

    public imprimirDoc(orden:any){
        window.open(this.apiService.baseUrl + '/api/orden/impresion/' + orden.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

}
