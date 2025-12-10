import { Component, OnInit,TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { Location } from '@angular/common';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-venta',
  templateUrl: './venta.component.html'
})
export class VentaComponent implements OnInit {

    public venta:any = {};
    public proyecto:any ={};
    public usuario:any = {};
    public loading = false;
    public saving = false;
    public type: string = '';

    modalRef!: BsModalRef;
    public filtros:any = {
        bandera: true,
    };

    public customFields:any = [];

    constructor( public apiService:ApiService, private alertService:AlertService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
        private location: Location
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
        this.route.data.subscribe(data => {
            this.type = data['type']; // 'venta' o 'cotizacion'
          });
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();
    }

    // public loadAll(){
    //     if(this.modalRef){
    //         this.modalRef.hide();
    //     }
        
    //     this.venta.id = +this.route.snapshot.paramMap.get('id')!;
    //     this.loading = true;
    //     const endpoint = this.type === 'cotizacion' ? 'cotizacion/' : 'venta/';
    //     if(this.type === 'cotizacion'){
    //         this.apiService.getAll('custom-fields',this.filtros).subscribe(customFields => {
    //             this.customFields = customFields;
    //         }, error => {
    //             this.alertService.error(error);
    //         });
    //     }

    //     //this.apiService.read('venta/', this.venta.id).subscribe(venta => {
    //     this.apiService.read(endpoint, this.venta.id).subscribe(venta => {
    //     this.venta = venta;
    //     const isCotizacion = this.type === 'cotizacion' ? true : false;
    //     this.venta.cotizacion = isCotizacion ? 1 : 0;

    //     if(this.venta.id_proyecto){
    //         this.apiService.read('proyecto/',this.venta.id_proyecto).subscribe(proyecto => {
    //             this.proyecto = proyecto;
    //             this.loading = false;
    //         }, error => {this.alertService.error(error); this.loading = false;});

    //     }

    //     this.loading = false;
    //     }, error => {this.alertService.error(error); this.loading = false;});

    // }

    public loadAll() {
        if (this.modalRef) {
            this.modalRef.hide();
        }
        
        this.venta.id = +this.route.snapshot.paramMap.get('id')!;
        this.loading = true;
        const endpoint = this.type === 'cotizacion' ? 'cotizacion/' : 'venta/';

        // Cargar custom fields si es cotización
        if (this.type === 'cotizacion') {
            this.apiService.getAll('custom-fields', this.filtros).subscribe(
                customFields => {
                    this.customFields = customFields;
                    // Continuar con la carga de la cotización después de obtener los campos
                    this.loadCotizacion();
                },
                error => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            );
        } else {
            this.loadCotizacion();
        }
    }

    private loadCotizacion() {
        this.apiService.read(this.type === 'cotizacion' ? 'cotizacion/' : 'venta/', this.venta.id)
            .subscribe(
                venta => {
                    this.venta = venta;
                    this.venta.cotizacion = this.type === 'cotizacion' ? 1 : 0;

                    if (this.venta.id_proyecto) {
                        this.loadProyecto();
                    } else {
                        this.loading = false;
                    }
                },
                error => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            );
    }

    private loadProyecto() {
        this.apiService.read('proyecto/', this.venta.id_proyecto).subscribe(
            proyecto => {
                this.proyecto = proyecto;
                this.loading = false;
            },
            error => {
                this.alertService.error(error);
                this.loading = false;
            }
        );
    }


    

    public setEstado(abono:any){
        this.saving = false;
        this.apiService.store('venta/abono', abono).subscribe(abono => {
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public imprimirRecibo(abono:any){
        window.open(this.apiService.baseUrl + '/api/venta/abono/imprimir/' + abono.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public openAbono(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.modalRef = this.modalService.show(template);
    }

    hasCustomField(fieldId: number): boolean {

        return this.venta.detalles?.some((detalle: any) => 
            detalle.custom_fields?.some((cf: any) => cf.custom_field?.id === fieldId)
        ) || false;
    }

    getCustomFieldValue(detalle: any, fieldId: number): string {
        const customField = detalle.custom_fields?.find(
            (cf: any) => cf.custom_field?.id === fieldId
        );
        return customField ? customField.value : '';
    }

    public goBack() {
        this.location.back();
    }

}
