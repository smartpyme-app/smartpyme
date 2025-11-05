import { Component, OnInit, OnDestroy, ViewChild, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { SwUpdate } from '@angular/service-worker';
import { ApiService } from '@services/api.service';
import { ConstantsService } from '@services/constants.service';
import { ChatService } from '@services/chat/chat.service';

// Tour deshabilitado temporalmente por incompatibilidad con Angular 20
// import {
//     IStepOption,
//     TourAnchorNgxBootstrapDirective,
//     TourNgxBootstrapModule,
//     TourService
// } from 'ngx-ui-tour-ngx-bootstrap';


@Component({
    selector: 'app-root',
    templateUrl: './app.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule]
})
export class AppComponent implements OnInit {
    public usuario: any = {};
    // public tourSteps: IStepOption[] = [];

    @ViewChild('mtour') tourTemplate!: TemplateRef<any>;
    @ViewChild('mtourend') tourEndTemplate!: TemplateRef<any>;
    modalRefStarTour!: BsModalRef;
    modalRefEndTour!: BsModalRef;

    constructor(private updates: SwUpdate, public apiService: ApiService, public alertService: AlertService,
        /* private tourService: TourService, */ private modalService: BsModalService, private chatService: ChatService,
        private constantsService: ConstantsService,
    ) {

        if (this.updates.isEnabled) {
            this.updates.versionUpdates.subscribe((event: any) => {
                if (event.type === 'VERSION_READY') {
                    // if (confirm('Hay una nueva versión disponible. ¿Quieres actualizar?')) {
                      this.updates.activateUpdate().then(() => document.location.reload());
                    // }
                }
            });
        }
    }
    

    ngOnInit() {

        this.chatService.resetChat();
        this.usuario = this.apiService.auth_user();
        
        // Cargar constantes si no están disponibles
        this.loadConstantsIfNeeded();
        
        // Tour deshabilitado temporalmente por incompatibilidad con Angular 20
        /* 
        this.tourSteps = [
            {
                anchorId: 'tour.resumen',
                content: 'Te mostramos un resumen rápido de como va tu negocio.',
                title: 'Indicadores',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.filtros',
                content: 'Filtra rápidamente para ver la información que más te interesa.',
                title: 'Filtros',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.sidebar',
                content: 'Aquí encontrarás todas las funciones de Smartpyme.<br> <b>Registra:</b> productos, servicios, ventas, compras, gastos y presupuestos.',
                title: 'Menú',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.sidebar_toogle',
                content: 'Puedes minimizar o maximizar este menú a tu gusto para tener más espacio.',
                title: 'Minimiza el menú',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.configuracion',
                content: 'Inicia por agregar toda la información de Demo SmartPyme en "Mi cuenta" dentro de configuraciones. Aquí podrás ingresar tu logo, datos de tu empresa, como también las preferencias del sistema.',
                title: 'Configuraciones',
                // route: '/productos',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.productos',
                content: 'Registra tus productos en "inventario".',
                title: 'Productos',
                route: '/productos',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.toolbar',
                content: 'En cada modulo tendrás opciones para buscar, filtrar, importa y exportar registros.',
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
                // route: '/productos',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.notificaciones',
                content: 'Aquí recibirás tus recordatorios tanto de stock bajos, cuentas por cobrar, cuentas por pagar y mucho más.',
                title: 'Notificaciones',
                // route: '/productos',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.reportes',
                content: 'En nuestro módulo de Inteligencia de Negocios, acá puedes ver los resultados de tu negocio.',
                title: 'Reportes',
                route: '/',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            }
        ];

        // if (this.usuario.tour_bienvenida == '0' && this.usuario.tipo == 'Administrador') {

        //     setTimeout(() => {
        //         if(!localStorage.getItem('sp_tour_iniciado')){
        //             this.modalRefStarTour = this.modalService.show(this.tourTemplate, {class: 'modal-md', backdrop: 'static', keyboard: false});
        //         }
        //     }, 1000);
        // }
        if (this.usuario.tour_bienvenida == '0' && this.apiService.validateRole('admin', true) || this.apiService.validateRole('super_admin', true)) {

            setTimeout(() => {
                if(!localStorage.getItem('sp_tour_iniciado')){
                    this.modalRefStarTour = this.modalService.show(this.tourTemplate, {class: 'modal-md', backdrop: 'static', keyboard: false});
                }
            }, 1000);
        }
        */

    }

    // Tour deshabilitado temporalmente por incompatibilidad con Angular 20
    /*
    starTour(){
        if(this.modalRefStarTour){
            this.modalRefStarTour.hide();
        }
        this.tourService.initialize(this.tourSteps);
        this.tourService.start();
        localStorage.setItem('sp_tour_iniciado', 'true');

        this.tourService.end$.subscribe(event => {
            this.modalRefEndTour = this.modalService.show(this.tourEndTemplate, {class: 'modal-md', backdrop: 'static', keyboard: false});
            this.saveTour();
            localStorage.setItem('sp_tour_iniciado', 'false');
        });
    }
    */

    omitirTour(){
        this.saveTour();
        localStorage.setItem('sp_tour_iniciado', 'false');
        if(this.modalRefStarTour){
            this.modalRefStarTour.hide();
        }

    }

    saveTour(){
        this.usuario.tour_bienvenida = true;
        this.apiService.store('usuario', this.usuario).subscribe(usuario => {
        },error => {this.alertService.error(error); });
    }

    private loadConstantsIfNeeded() {
        const constants = localStorage.getItem('SP_constants');
        if (!constants) {
            // console.log('Cargando constantes...');
            this.constantsService.loadConstants().subscribe(
                (constants) => {
                    console.log('Constantes cargadas en app component:', constants);
                },
                (error) => {
                    console.error('Error cargando constantes en app component:', error);
                }
            );
        } else {
            // console.log('Constantes ya disponibles en localStorage');
        }
    }


}
