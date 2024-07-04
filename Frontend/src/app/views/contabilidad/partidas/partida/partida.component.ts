import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe }     from '@pipes/sum.pipe';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-partida',
  templateUrl: './partida.component.html',
  providers: [ SumPipe ]
})
export class PartidaComponent implements OnInit {

    public partida:any = {};
    public catalogos:any = [];
    public loading = false;
    public saving = false;
    modalRef?: BsModalRef;

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
            this.apiService.read('partida/', id).subscribe(partida => {
                this.partida = partida;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.partida = {};
            this.partida.fecha = this.apiService.date();
            this.partida.estado = 'Pendiente';
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

        this.apiService.store('partida', this.partida).subscribe(partida => {
            if (!this.partida.id) {
                this.alertService.success('Partida guardada', 'La partida fue guardada exitosamente.');
            }else{
                this.alertService.success('Partida creada', 'La partida fue añadida exitosamente.');
            }
            this.router.navigate(['/contabilidad/partidas']);
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
