import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';

@Component({
    selector: 'app-cliente-ventas',
    templateUrl: './cliente-ventas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    
})
export class ClienteVentasComponent extends BaseCrudComponent<any> implements OnInit {

    public id: any;
	public ventas: any = {};

    public filtro: any = {};

    constructor(
        apiService: ApiService, 
        alertService: AlertService,  
    	private route: ActivatedRoute, 
        private router: Router,
    	modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'venta',
            itemsProperty: 'ventas',
            itemProperty: 'venta',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La venta fue actualizada exitosamente.',
                updated: 'La venta fue actualizada exitosamente.',
                deleted: 'Venta eliminada exitosamente.',
                createTitle: 'Venta actualizada',
                updateTitle: 'Venta actualizada',
                deleteTitle: 'Venta eliminada',
                deleteConfirm: '¿Desea eliminar el Registro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.onFiltrar();
    }

	ngOnInit() {
        this.loadAll();
        
        this.filtro.estado = "";
        this.filtro.metodo_pago = "";

        if(this.route.snapshot.paramMap.get('estado')){
            this.filtro.estado = this.route.snapshot.paramMap.get('estado');
        }

        this.filtro.fecha = this.apiService.date();

    }

    public override loadAll() {
        this.id = +this.route.snapshot.paramMap.get('id')!;
                	        
        if(isNaN(this.id)){
            this.ventas = {};
        }
        else{
            this.loading = true;
            this.apiService.read('cliente/ventas/', this.id)
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (ventas) => {
                        this.ventas = ventas;
                        this.loading = false;
                    },
                    error: (error) => {
                        this.alertService.error(error);
                        this.loading = false;
                    }
                });
        }
    }

    onFiltrar(){
        this.filtro.id = this.id;
        this.loading = true;
        this.apiService.store('cliente/ventas/filtrar', this.filtro)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (ventas) => {
                    this.ventas = ventas;
                    this.loading = false;
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }

    public override delete(id: number) {
        const index = this.ventas.data?.findIndex((v: any) => v.id === id);
        if (index !== -1 && index >= 0) {
            this.ventas.data.splice(index, 1);
        }
    }

    public setEstado(venta: any, estado: string){
        venta.estado = estado;
        this.onSubmit(venta, true);
    }

    override openModal(template: TemplateRef<any>) {
        super.openModal(template);
    }

    public cobrarTodo(){
    	if (confirm('¿Confirma marcar todas las ventas como cobradas?')) {
            this.cobrar();
    	}
    }

    public imprimir(){
        window.open(this.apiService.baseUrl + '/api/cliente/estado-de-cuenta/' + this.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    public imprimirCobrar(){
        if (confirm('¿Confirma marcar todas las ventas como cobradas?')) {
            this.cobrar();
            setTimeout(() => {
                window.print();
            },2000)
        }
    }


    cobrar(){
        for (var i = 0; i < this.ventas.data.length; ++i) {
            if(this.ventas.data[i].estado == 'Pendiente') {
                this.ventas.data[i].estado = 'Cobrada';
                this.apiService.store('venta', this.ventas.data[i]).pipe(this.untilDestroyed()).subscribe(venta => {
                }, error => {this.alertService.error(error); });
            }
        }
        this.loadAll();
        this.closeModal();
    }



}
