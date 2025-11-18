import { Component, OnInit,TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe }     from '@pipes/sum.pipe';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PartidaDetallesComponent } from './detalles/partida-detalles.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import * as moment from 'moment';

@Component({
    selector: 'app-partida',
    templateUrl: './partida.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PartidaDetallesComponent],
    providers: [SumPipe],
    
})
export class PartidaComponent implements OnInit {

    public partida:any = {};
    public catalogos:any = [];
    public proveedor: any = {};
    public cliente: any = {};
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor( 
      private apiService: ApiService, private alertService: AlertService, private sumPipe:SumPipe,
      private route: ActivatedRoute, private router: Router, private modalService: BsModalService
  ) { }

  ngOnInit() {
        this.loadAll();

        // this.apiService.getAll('catalogos/list').subscribe(catalogos => {
        //     this.catalogos = catalogos;
        // }, error => {this.alertService.error(error);});
    }

    public loadAll(){
        const id = +this.route.snapshot.paramMap.get('id')!;
        if (id) {
            this.loading = true;
            this.apiService.read('partida/', id)
              .pipe(this.untilDestroyed())
              .subscribe(partida => {
                this.partida = partida;
                this.sumTotal();
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.partida = {};
            this.partida.fecha = this.apiService.date();
            this.partida.estado = 'Pendiente';
            this.partida.tipo = 'Ingreso';
            this.partida.detalles = [];
            this.partida.id_usuario = this.apiService.auth_user().id;
            this.partida.id_empresa = this.apiService.auth_user().id_empresa;
        }

    }

    public sumTotal() {
        this.partida.debe = (parseFloat(this.sumPipe.transform(this.partida.detalles, 'debe'))).toFixed(2);
        this.partida.haber = (parseFloat(this.sumPipe.transform(this.partida.detalles, 'haber'))).toFixed(2);
        this.partida.diferencia = (this.partida.debe - this.partida.haber).toFixed(2);
    }

    public updatePartida(partida:any) {
        this.partida = partida;
        this.sumTotal();
    }

    public onSubmit(){
        this.saving = true;

        console.log(this.partida.detalles);

        this.apiService.store('partida', this.partida)
          .pipe(this.untilDestroyed())
          .subscribe(partida => {
            if (!this.partida.id) {
                this.alertService.success('Partida guardada', 'La partida fue guardada exitosamente.');
            }else{
                this.alertService.success('Partida creada', 'La partida fue añadida exitosamente.');
            }
            this.router.navigate(['/contabilidad/partidas']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    openModalProveedor(template: TemplateRef<any>) {

            this.proveedor = {};
            this.proveedor.tipo = 'Persona';
            this.proveedor.id_usuario = this.apiService.auth_user().id;
            this.proveedor.id_empresa = this.apiService.auth_user().id_empresa;
        
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public setTipo(tipo:any){
        this.proveedor.tipo = tipo;
    }
    
    public onSubmitProveedor() {
        this.saving = true;
        this.apiService.store('proveedor', this.proveedor)
          .pipe(this.untilDestroyed())
          .subscribe(proveedor => {
            // this.update.emit(proveedor);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
            this.alertService.success('Proveedor creado', 'Tu proveedor fue añadido exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });
    }

    openModalCliente(template: TemplateRef<any>) {
            this.cliente = {};
            this.cliente.tipo = 'Persona';
            this.cliente.id_usuario = this.apiService.auth_user().id;
            this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
        
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    openModal(template: TemplateRef<any>) {
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }

    public setTipoCliente(tipo:any){
        this.cliente.tipo = tipo;
    }

    public onSubmitCliente() {
        this.saving = true;
        this.apiService.store('cliente', this.cliente)
          .pipe(this.untilDestroyed())
          .subscribe(cliente => {
            // this.update.emit(cliente);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
            this.alertService.success('Cliente creado', 'El cliente ha sido agregado.');
        },error => {this.alertService.error(error); this.saving = false; });
    }

    generarPartidasDelDia(){
        this.saving = true;
        this.apiService.store('partidas/generar/' + this.partida.tipo.toLowerCase() , this.partida)
          .pipe(this.untilDestroyed())
          .subscribe(data => {
            this.partida = data.partida;
            this.partida.id_usuario = this.apiService.auth_user().id;
            this.partida.id_empresa = this.apiService.auth_user().id_empresa;

            this.partida.detalles = data.detalles;
            if(this.partida.detalles.length == 0){
                this.alertService.info('No hay registros', 'No se encontraron transacciones.')
            }else{
                this.sumTotal();
                this.modalRef?.hide();
            }

            this.saving = false;
            
        }, error => {
          this.alertService.error(error);
          this.saving = false;
        });
      }

}
