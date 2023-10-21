import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-mesas-dash',
  templateUrl: './mesas-dash.component.html'
})
export class MesasDashComponent implements OnInit {

    @Input() dash: any = {};
    @Input() loading:boolean = false;
    @Output() loadAll = new EventEmitter();

    public comanda: any = {};
    public estado:string = '';

    modalRef!: BsModalRef;

    constructor( 
          private apiService: ApiService, private alertService: AlertService,
          private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        
    }

    openModal(template: TemplateRef<any>, comanda:any) {
        this.comanda = comanda;
        this.modalRef = this.modalService.show(template);
    }
    
    public selectComanda(comanda:any){
        this.router.navigate(['/comanda/' + comanda.id]);
    }

    public setEstado(comanda:any, estado:any){
        if (estado == 'Entregada') {
            if (confirm("¿Confirma que la comanda esta lista?")){
                this.comanda = comanda;
                this.comanda.estado = estado;
                for (var i = 0; i < this.comanda.detalles.length; ++i) {
                    this.setEstadoDetalle(this.comanda.detalles[i], estado);
                }
                this.onSubmit();
            }
        }else{
            this.comanda = comanda;
            this.comanda.estado = estado;
            for (var i = 0; i < this.comanda.detalles.length; ++i) {
                this.setEstadoDetalle(this.comanda.detalles[i], estado);
            }
            this.onSubmit();
        }
    }

    public onSubmit() {
          this.loading = true;
          // Guardamos la comanda
          this.apiService.store('comanda', this.comanda).subscribe(comanda => {
                if (comanda.estado == 'Entregada') {
                    this.comanda = {};
                }
                // this.alertService.success("Datos guardados");
                this.loading = false;
                this.loadAll.emit();
          },error => {this.alertService.error(error); this.loading = false; });
    }

    public setEstadoDetalle(detalle:any, estado:string) {
          this.loading = true;
          detalle.estado = estado;
          this.apiService.store('comanda/detalle', detalle).subscribe(detalle => {
                detalle = detalle;
                // this.alertService.success("Datos guardados");
                this.loading = false;
          },error => {this.alertService.error(error); this.loading = false; });
    }

}
