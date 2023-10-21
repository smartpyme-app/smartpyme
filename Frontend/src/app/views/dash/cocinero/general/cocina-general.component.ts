import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-cocina-general',
  templateUrl: './cocina-general.component.html'
})
export class CocinaGeneralComponent implements OnInit {

    @Input() dash: any = {};
    @Input() loading:boolean = false;
    public dashResfresh:any;

    public comanda:any = {};
    public estado:string = '';

    modalRef!: BsModalRef;

    constructor( 
          private apiService: ApiService, private alertService: AlertService,
          private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.loading = true;

        this.apiService.getAll('dash/cocinero').subscribe(dash => {
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
        
        this.dashResfresh = setInterval(()=> {
            if (!this.loading)
                this.loadAll();
        }, 25000);

    }
    
    public loadAll(){
        this.loading = true;
        this.apiService.getAll('dash/cocinero').subscribe(dash => {
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    ngOnDestroy(){
        clearInterval(this.dashResfresh);

    }


    openModal(template: TemplateRef<any>, comanda:any) {
        this.comanda = comanda;
        this.modalRef = this.modalService.show(template);
    }
    

    public selectComanda(comanda:any){
        if (this.comanda.id && this.comanda.id == comanda.id) {
            this.getComanda();
        }
        else{
            this.comanda = comanda;
            this.comanda.detalles = this.comanda.detalles.filter((item:any) => item.estado != 'Entregada' && item.estado != 'Agregada');
        }
    }

    public getComanda(){
        if (this.comanda.id) {
            this.apiService.read('comanda/', this.comanda.id).subscribe(comanda => {
                  this.comanda = comanda;
                  this.comanda.detalles = this.comanda.detalles.filter((item:any) => item.estado != 'Entregada' && item.estado != 'Agregada');
            },error => {this.alertService.error(error); this.loading = false; });
        }
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
                this.loading = false;
          },error => {this.alertService.error(error); this.loading = false; });
    }

    public setEstadoDetalle(detalle:any, estado:string) {
          this.loading = true;
          detalle.estado = estado;
          this.apiService.store('comanda/detalle', detalle).subscribe(detalle => {
                detalle = detalle;
                this.comanda.detalles = this.comanda.detalles.filter((item:any) => item.estado != 'Entregada' && item.estado != 'Agregada');
                if (detalle.pendientes == 0) {
                    this.comanda = {};
                }
                this.loadAll();
                this.loading = false;
          },error => {this.alertService.error(error); this.loading = false; });
    }

}
