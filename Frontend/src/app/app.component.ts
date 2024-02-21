import { Component } from '@angular/core';

@Component({
    selector: 'app-root',
    templateUrl: './app.component.html'
})
export class AppComponent {


    ngOnInit() {


        this.usuario = this.apiService.auth_user();
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
                content: 'Accede a todos los módulos de tu plan. <br> <b>Registra:</b> productos, servicios, ventas, compras, gastos y presupuestos.',
                title: 'Menú',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.sidebar_toogle',
                content: 'Puedes minimizar o maximizar este menú a tu gusto para tener más espacio.',
                title: 'Minimiza el menú',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.crear_venta',
                content: 'Crea ventas rápidamente desde este atajo.',
                title: 'Crear venta',
                // route: '/productos',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.configuracion',
                content: 'Gestiona usuarios, sucursales y la información de ' + this.usuario.empresa.nombre + ' desde acá.',
                title: 'Configuración',
                // route: '/productos',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            },{
                anchorId: 'tour.cerrar_sesion',
                content: 'Desde acá puedes cerrar sesión.',
                title: 'Cerrar sesión',
                // route: '/productos',
                enableBackdrop: true, prevBtnTitle: 'Antes', nextBtnTitle: 'Siguiente', endBtnTitle: 'Finalizar'
            }
        ];

        if (!localStorage.getItem('sp_tour')) {

            // setTimeout(() => {
            //     console.log(this.tourTemplate);
            //     this.modalRef = this.modalService.show(this.tourTemplate, {class: 'modal-md', backdrop: 'static', keyboard: false});
            // }, 1000);
            
            // this.tourService.end$.subscribe(event => {
            //     this.omitirTour();
            // });
        }

    }

    starTour(){
        if(this.modalRef){
            this.modalRef.hide();
        }

        this.tourService.initialize(this.tourSteps);
        this.tourService.start();
    }

    omitirTour(){
        console.log('El recorrido ha finalizado.');
        localStorage.setItem('sp_tour', 'true');
        if(this.modalRef){
            this.modalRef.hide();
        }
    }

}
