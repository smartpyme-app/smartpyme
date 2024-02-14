import { Component } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import {
    IStepOption,
    TourAnchorNgxBootstrapDirective,
    TourNgxBootstrapModule,
    TourService
} from 'ngx-ui-tour-ngx-bootstrap';


@Component({
    selector: 'app-root',
    templateUrl: './app.component.html'
})
export class AppComponent {
    public usuario: any = {};
    public tourSteps: IStepOption[] = [];
    constructor(public apiService: ApiService, public alertService: AlertService, private tourService: TourService) { }
    

    ngOnInit() {


        this.usuario = this.apiService.auth_user();
        this.tourSteps = [{
            anchorId: 'tour.resumen',
            content: 'Conoce rápidamente como va tu negocio con estos indicadores.',
            title: 'Resumen de ventas',
            enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
        },{
            anchorId: 'tour.filtros',
            content: 'Puedes filtrar facilmente los datos acá.',
            title: 'Filtros',
            enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
        },{
            anchorId: 'tour.sidebar',
            content: 'Accede a todos los módulos desde acá.',
            title: 'Menú',
            enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
        },{
            anchorId: 'tour.sidebar_toogle',
            content: 'Puedes minimizar en menú para tener más espacio.',
            title: 'Minimiza el menú',
            enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
        },{
            anchorId: 'tour.crear_venta',
            content: 'Desde acá puedes crear ventas rápidamente.',
            title: 'Agregar ventas',
            // route: '/productos',
            enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
        },{
            anchorId: 'tour.configuracion',
            content: 'Gestiona los usuarios y sucursales de tu negocio desde acá.',
            title: 'Configuración',
            // route: '/productos',
            enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
        },{
            anchorId: 'tour.cerrar_sesion',
            content: 'Desde acá puedes cerrar sesión para proteger tu cuenta.',
            title: 'Cerrar sesión',
            // route: '/productos',
            enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
        }];

            this.tourService.enableHotkeys();
            this.tourService.initialize(this.tourSteps);
            if (!localStorage.getItem('sp_tour')) {
                console.log('No')
                // this.tourService.start();
            }


        this.tourService.end$.subscribe(event => {
              console.log('El recorrido ha finalizado.');
                // localStorage.setItem('sp_tour', 'true');
        });
    }

}
