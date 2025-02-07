import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-orden-produccion',
  templateUrl: './crear-orden-produccion.component.html'
})
export class CrearOrdenProduccionComponent implements OnInit {
  public orden: any = {
    fecha: new Date().toISOString().split('T')[0],
    estado: 'pendiente',
    detalles: []
  };
  public cotizacion: any = {};
  public loading: boolean = false;
  public customFields: any = [];
  public isDetalles: boolean = false;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private route: ActivatedRoute
  ) { }

  ngOnInit() {
    this.isDetalles = this.router.url.includes('/detalles/');
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      if (this.isDetalles) {
        this.cargarOrden(Number(id));
      } else {
        this.cargarCotizacion(Number(id));
      }
    } else {
      this.alertService.error('Debe seleccionar una cotización');
      this.router.navigate(['/cotizaciones']);
    }
  }

  cargarOrden(id: number) {
    this.loading = true;
    this.apiService.read('orden-produccion/', id).subscribe(
      (response: any) => {
       
        this.cotizacion = response

        this.orden = {
          fecha: new Date().toISOString().split('T')[0],
          fecha_entrega: new Date(this.cotizacion.fecha_entrega).toISOString().split('T')[0],
          estado: 'pendiente', 
          id_cotizacion: this.cotizacion.id,
          id_cliente: this.cotizacion.id_cliente,
          id_usuario: this.cotizacion.id_usuario,
          id_asesor: this.cotizacion.id_vendedor,
          id_empresa: this.cotizacion.id_empresa,
          id_sucursal: this.cotizacion.id_sucursal,
          observaciones: this.cotizacion.observaciones,
          subtotal: this.cotizacion.sub_total,
          total_costo: this.cotizacion.total_costo,
          descuento: this.cotizacion.descuento,
          total: this.cotizacion.total,
          terminos_de_venta: this.cotizacion.terminos_de_venta,
          correlativo: this.cotizacion.correlativo,
          nombre_cliente: this.cotizacion.nombre_cliente,
          nombre_usuario: this.cotizacion.nombre_usuario,
          nombre_vendedor: this.cotizacion.nombre_vendedor,
          nombre_sucursal: this.cotizacion.nombre_sucursal,
          detalles: this.cotizacion.detalles.map((detalle: any) => ({
            id_producto: detalle.id_producto,
            cantidad: detalle.cantidad,
            descripcion: detalle.descripcion,
            precio: detalle.precio,
            total: detalle.total,
            total_costo: detalle.total_costo,
            descuento: detalle.descuento,
            id_cotizacion_venta: detalle.id_cotizacion_venta,
            custom_fields: detalle.custom_fields || [],
            producto: detalle.producto
          }))
        };

        this.loadCustomFields();
      },
      error => {
        this.alertService.error('Error al cargar la orden');
        this.loading = false;
        this.router.navigate(['/ordenes-produccion']);
      }
    );
  }

  cargarCotizacion(id: number) {

    console.log('id', id)
    this.loading = true;
    this.apiService.read('cotizacion/', id).subscribe(
      (response: any) => {
        this.cotizacion = response
        
        if (this.cotizacion.estado !== 'Aprobada') {
          this.alertService.error('Solo se pueden crear órdenes de producción de cotizaciones aprobadas');
          this.router.navigate(['/cotizaciones']);
          return;
        }

        this.orden = {
          fecha: new Date().toISOString().split('T')[0],
          fecha_entrega: new Date().toISOString().split('T')[0],
          estado: 'pendiente',
          id_cotizacion: this.cotizacion.id,
          id_cliente: this.cotizacion.id_cliente,
          id_usuario: this.cotizacion.id_usuario,
          id_asesor: this.cotizacion.id_vendedor,
          id_empresa: this.cotizacion.id_empresa,
          id_bodega: this.cotizacion.id_bodega,
          id_sucursal: this.cotizacion.id_sucursal,
          observaciones: this.cotizacion.observaciones,
          subtotal: this.cotizacion.sub_total,
          total_costo: this.cotizacion.total_costo,
          descuento: this.cotizacion.descuento,
          terminos_de_venta: this.cotizacion.terminos_de_venta,
          total: this.cotizacion.total,
          correlativo: this.cotizacion.correlativo,
          nombre_cliente: this.cotizacion.nombre_cliente,
          nombre_usuario: this.cotizacion.nombre_usuario,
          nombre_vendedor: this.cotizacion.nombre_vendedor,
          nombre_sucursal: this.cotizacion.nombre_sucursal,
          detalles: this.cotizacion.detalles.map((detalle: any) => ({
            id_producto: detalle.id_producto,
            cantidad: detalle.cantidad,
            descripcion: detalle.descripcion,
            precio: detalle.precio,
            total: detalle.total,
            total_costo: detalle.total_costo,
            descuento: detalle.descuento,
            id_cotizacion_venta: detalle.id_cotizacion_venta,
            custom_fields: detalle.custom_fields || [],
            producto: detalle.producto
          }))
        };

        // Cargar custom fields después de tener la cotización
        this.loadCustomFields();
      },
      error => {
        this.alertService.error('Error al cargar la cotización');
        this.loading = false;
        this.router.navigate(['/cotizaciones']);
      }
    );
  }

  loadCustomFields() {
    this.apiService.getAll('custom-fields', { bandera: true }).subscribe(
      response => {
        this.customFields = response.data;
        this.loading = false;
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  hasCustomField(fieldId: number): boolean {
    return this.orden.detalles?.some((detalle: any) => 
      detalle.custom_fields?.some((cf: any) => cf.custom_field_id === fieldId)
    ) || false;
  }

  getCustomFieldValue(detalle: any, fieldId: number): string {
    const customField = detalle.custom_fields?.find(
      (cf: any) => cf.custom_field_id === fieldId
    );
    return customField ? customField.value : '';
  }

  guardar() {
    if (!this.orden.fecha_entrega) {
      this.alertService.error('Debe especificar una fecha de entrega');
      return;
    }
    if (new Date(this.orden.fecha_entrega) < new Date(this.orden.fecha)) {
      this.alertService.error('La fecha de entrega no puede ser anterior a la fecha actual');
      return;
    }

    this.loading = true;
    this.apiService.store('orden-produccion', this.orden).subscribe(
      response => {
        this.alertService.success('Orden creada', 'La orden de producción fue creada exitosamente.');
        this.router.navigate(['/ordenes/produccion']);
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  
  cancelar() {
    if (this.isDetalles) {
      this.router.navigate(['/ordenes/produccion']);
    } else {
      this.router.navigate(['/cotizaciones']);
    }
  }


  formatDate(date: string): string {
    return new Date(date).toISOString().split('T')[0];
  }
}