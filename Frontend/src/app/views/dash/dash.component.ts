import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import {
    IStepOption,
    TourAnchorNgxBootstrapDirective,
    TourNgxBootstrapModule,
    TourService
} from 'ngx-ui-tour-ngx-bootstrap';

@Component({
  selector: 'app-dash',
  templateUrl: './dash.component.html'
})
export class DashComponent implements OnInit {

    public usuario:any;
    public saludo:any;

    public tourSteps: IStepOption[] = [{
        anchorId: 'tour.inicio',
        content: 'Bienvenido a SmartPyme!',
        title: 'Bienvenido',
        enableBackdrop: true,
        prevBtnTitle: 'Antes',
        nextBtnTitle: 'Siguiente',
        endBtnTitle: 'Finalizar'
    },{
        anchorId: 'tour.filtros',
        content: 'Puedes filtrar facilmente los datos acá!',
        title: 'Filtros',
        enableBackdrop: true,
        prevBtnTitle: 'Antes',
        nextBtnTitle: 'Siguiente',
        endBtnTitle: 'Finalizar'
    }];

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private router: Router, private tourService: TourService
    ) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();        
        this.saludo = this.apiService.saludar();

        if(this.usuario.tipo == 'Ventas'){
            this.router.navigate(['/vendedor/ventas']);    
        }
        if(this.usuario.tipo == 'Citas'){
            this.router.navigate(['/citas']);    
        }

        this.tourService.initialize(this.tourSteps);
        // this.tourService.start();

    }



}
