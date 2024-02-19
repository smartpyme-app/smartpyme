import { Component, OnInit, ViewChild, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
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

    public saludo:any;
    public usuario: any = {};
    public tourIniciado:boolean = false;
    public tourSteps: IStepOption[] = [];

    @ViewChild('mtour') tourTemplate!: TemplateRef<any>;
    @ViewChild('mtourend') tourEndTemplate!: TemplateRef<any>;
    modalRefStarTour!: BsModalRef;
    modalRefEndTour!: BsModalRef;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private router: Router, private tourService: TourService, private modalService: BsModalService
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

        this.tourSteps = [
            {
                anchorId: 'tour.resumen',
                content: 'Te mostramos un resumen rápido de como va tu negocio.',
                title: 'Indicadores',
                route: '/',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.filtros',
                content: 'Filtra rápidamente para ver la información que más te interesa.',
                title: 'Filtros',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.sidebar',
                content: 'Aquí encontrarás todas las funciones de Smartpyme.. <br> <b>Registra:</b> productos, servicios, ventas, compras, gastos y presupuestos.',
                title: 'Menú',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.sidebar_toogle',
                content: 'Puedes minimizar o maximizar este menú para tener más espacio.',
                title: 'Minimiza el menú',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.configuracion',
                content: 'Inicia por agregar toda la información de ' + this.usuario.empresa.nombre + ' en "Mi cuenta" dentro de configuraciones. Aquí podrás ingresar tu logo, datos de tu empresa, como también las preferencias del sistema.',
                title: 'Configuraciones',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.productos',
                content: 'Registra tus productos en "inventario".',
                title: 'Productos',
                route: '/productos',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.productos.toolbar',
                content: 'En cada modulo tendras opciones para buscar, filtrar, importa y exportar registros.',
                title: 'Toolbar',
                route: '/productos',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.crear_venta',
                content: 'Crea ventas rápidamente desde este atajo.',
                title: 'Crear venta',
                // route: '/productos',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.cierre_caja',
                content: 'Aquí podrás ver el cierre de caja diario, pero también podrás aplicar filtros por días y descargar la información.',
                title: 'Cierre de caja',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.notificaciones',
                content: 'Aquí recibirás tus recordatorios tanto de stock bajos, cuentas por cobrar, cuentas por pagar y mucho más.',
                title: 'Notificaciones',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.reportes',
                content: 'En nuestro módulo de <b>Inteligencia de Negocios</b>, acá puedes ver los resultados de tu negocio.',
                title: 'Reportes',
                route: '/',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            }
        ];

        // if (!localStorage.getItem('sp_tour')) {

        //     setTimeout(() => {
        //         if(!localStorage.getItem('sp_tour_iniciado')){
        //             this.modalRefStarTour = this.modalService.show(this.tourTemplate, {class: 'modal-md', backdrop: 'static', keyboard: false});
        //         }
        //     }, 1000);
            
        //     this.tourService.end$.subscribe(event => {
        //         this.modalRefEndTour = this.modalService.show(this.tourEndTemplate, {class: 'modal-md', backdrop: 'static', keyboard: false});
        //         localStorage.setItem('sp_tour', 'true');
        //         localStorage.setItem('sp_tour_iniciado', 'false');
        //     });
        // }

    }    

    starTour(){
        if(this.modalRefStarTour){
            this.modalRefStarTour.hide();
        }

        this.tourService.initialize(this.tourSteps);
        this.tourService.start();
        localStorage.setItem('sp_tour_iniciado', 'true');
    }

    omitirTour(){
        localStorage.setItem('sp_tour', 'true');
        localStorage.setItem('sp_tour_iniciado', 'false');
        if(this.modalRefStarTour){
            this.modalRefStarTour.hide();
        }
    }




}
