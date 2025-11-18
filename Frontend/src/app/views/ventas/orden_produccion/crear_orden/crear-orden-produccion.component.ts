import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FileService } from '@services/file.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-crear-orden-produccion',
    templateUrl: './crear-orden-produccion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearOrdenProduccionComponent implements OnInit {
  public orden: any = {
    fecha: new Date().toISOString().split('T')[0],
    estado: 'creada',
    detalles: [],
    // estado: 'pendiente',
  };
  public cotizacion: any = {};
  public loading: boolean = false;
  public customFields: any = [];
  public isDetalles: boolean = false;
  public editar: boolean = false;
  selectedFile: File | null = null;
  downloading: boolean = false;
  filtros: any = {
    id: null,
  };

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private fileService: FileService,
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit() {
    this.isDetalles =
      this.router.url.includes('/detalles/') ||
      this.router.url.includes('/editar/');

    //si la ruta inclue editar poner en true si no false
    this.editar = this.router.url.includes('/editar/') ? true : false;
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
    this.apiService.read('orden-produccion/', id)
      .pipe(this.untilDestroyed())
      .subscribe(
      (response: any) => {
        this.cotizacion = response;

        if (this.isDetalles && response.has_documento) {
          this.cotizacion.documento_adjunto =
            response.documento_orden.nombre_archivo;
          this.cotizacion.nombre_documento =
            response.documento_orden.nombre_archivo;
        }

        this.orden = {
          id: response.id,
          fecha: new Date().toISOString().split('T')[0],
          fecha_entrega: new Date(this.cotizacion.fecha_entrega)
            .toISOString()
            .split('T')[0],
          estado: this.cotizacion.estado,
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
          terminos_de_venta: this.cotizacion.terminos_condiciones,
          correlativo: this.cotizacion.correlativo,
          nombre_cliente: this.cotizacion.nombre_cliente,
          nombre_usuario: this.cotizacion.nombre_usuario,
          nombre_vendedor: this.cotizacion.nombre_vendedor,
          nombre_sucursal: this.cotizacion.nombre_sucursal,
          detalles: this.cotizacion.detalles.map((detalle: any) => ({
            id_producto: detalle.id_producto,
            cantidad: detalle.cantidad,
            cantidad_producida: detalle.cantidad_producida || 0,
            porcentaje: detalle.cantidad_producida
              ? (detalle.cantidad_producida / detalle.cantidad) * 100
              : 0,
            descripcion: detalle.descripcion,
            precio: detalle.precio,
            total: detalle.total,
            total_costo: detalle.total_costo,
            descuento: detalle.descuento,
            id_cotizacion_venta: detalle.id_cotizacion_venta,
            custom_fields: detalle.custom_fields || [],
            producto: detalle.producto,
            id: detalle.id,
          })),
        };

        this.loadCustomFields();
      },
      (error) => {
        this.alertService.error('Error al cargar la orden');
        this.loading = false;
        this.router.navigate(['/ordenes-produccion']);
      }
    );
  }

  cargarCotizacion(id: number) {
    //console.log('id', id)
    this.loading = true;
    this.apiService.read('cotizacion/', id)
      .pipe(this.untilDestroyed())
      .subscribe(
      (response: any) => {
        this.cotizacion = response;

        if (this.cotizacion.estado !== 'aceptada') {
          this.alertService.error(
            'Solo se pueden crear órdenes de producción de cotizaciones aceptadas'
          );
          this.router.navigate(['/cotizaciones']);
          return;
        }

        this.orden = {
          fecha: new Date().toISOString().split('T')[0],
          fecha_entrega: new Date().toISOString().split('T')[0],
          // estado: 'pendiente',
          estado: 'creada',
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
            cantidad_producida: detalle.cantidad_producida || 0,
            porcentaje: detalle.cantidad_producida
              ? (detalle.cantidad_producida / detalle.cantidad) * 100
              : 0,
            descripcion: detalle.descripcion,
            precio: detalle.precio,
            total: detalle.total,
            total_costo: detalle.total_costo,
            descuento: detalle.descuento,
            id_cotizacion_venta: detalle.id_cotizacion_venta,
            custom_fields: detalle.custom_fields || [],
            producto: detalle.producto,
          })),
        };

        // Cargar custom fields después de tener la cotización
        this.loadCustomFields();
      },
      (error) => {
        this.alertService.error('Error al cargar la cotización');
        this.loading = false;
        this.router.navigate(['/cotizaciones']);
      }
    );
  }

  loadCustomFields() {
    this.apiService.getAll('custom-fields', { bandera: true })
      .pipe(this.untilDestroyed())
      .subscribe(
      (response) => {
        this.customFields = response.data;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  hasCustomField(fieldId: number): boolean {
    return (
      this.orden.detalles?.some((detalle: any) =>
        detalle.custom_fields?.some((cf: any) => cf.custom_field_id === fieldId)
      ) || false
    );
  }

  getCustomFieldValue(detalle: any, fieldId: number): string {
    const customField = detalle.custom_fields?.find(
      (cf: any) => cf.custom_field_id === fieldId
    );
    return customField ? customField.value : '';
  }

  // guardar() {
  //   if (!this.orden.fecha_entrega) {
  //     this.alertService.error('Debe especificar una fecha de entrega');
  //     return;
  //   }
  //   if ((new Date(this.orden.fecha_entrega) < new Date(this.orden.fecha)) && !this.isDetalles) {
  //     this.alertService.error('La fecha de entrega no puede ser anterior a la fecha actual');
  //     return;
  //   }

  //   this.loading = true;

  //   this.apiService.store('orden-produccion', this.orden).subscribe(
  //     response => {
  //       if (!this.isDetalles) {
  //         this.alertService.success('Orden creada', 'La orden de producción fue creada exitosamente.');
  //         this.router.navigate(['/ordenes/produccion']);
  //       }
  //       this.alertService.success('Orden actualizada', 'La orden de producción fue actualizada exitosamente.');
  //       this.loading = false;

  //     },
  //     error => {
  //       this.alertService.error(error);
  //       this.loading = false;
  //     }
  //   );
  // }
  async guardar() {
    if (!this.orden.fecha_entrega) {
      this.alertService.error('Debe especificar una fecha de entrega');
      return;
    }
  
    if (new Date(this.orden.fecha_entrega) < new Date(this.orden.fecha)) {
      this.alertService.error(
        'La fecha de entrega no puede ser anterior a la fecha actual'
      );
      return;
    }
  
    try {
      this.loading = true;
  
      // Preparar FormData con el archivo y los datos
      const formData = this.fileService.prepareFormData(
        this.orden,
        this.selectedFile
      );
  
      // Enviar la petición
      const response = await this.apiService
        .store('orden-produccion', formData)
        .toPromise();
  
      // Manejar diferentes mensajes según el tipo de operación
      if (response.action === 'updated') {
        this.alertService.success(
          'Orden actualizada',
          'La orden de producción fue actualizada exitosamente.'
        );
      } else if (response.action === 'created') {
        this.alertService.success(
          'Orden creada',
          'La orden de producción fue creada exitosamente.'
        );
      } else {
        // Fallback en caso de que no venga el action
        this.alertService.success(
          this.isDetalles ? 'Orden actualizada' : 'Orden creada',
          this.isDetalles 
            ? 'La orden de producción fue actualizada exitosamente.'
            : 'La orden de producción fue creada exitosamente.'
        );
      }
  
      this.router.navigate(['/ordenes/produccion']);
    } catch (error) {
      this.alertService.error(error);
    } finally {
      this.loading = false;
    }
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

  updatePorcentaje(detalle: any) {
    if (!detalle.cantidad_producida) {
      detalle.cantidad_producida = 0;
      detalle.porcentaje = 0;
      return;
    }

    if (detalle.cantidad_producida > detalle.cantidad) {
      this.alertService.error(
        'La cantidad producida no puede ser mayor a la cantidad solicitada'
      );
      detalle.cantidad_producida = detalle.cantidad;
    }

    detalle.porcentaje = (detalle.cantidad_producida / detalle.cantidad) * 100;
  }

  public setEstado(orden: any) {
    //emviar en formData
    const formData = this.fileService.prepareFormData(orden, this.selectedFile);
    this.apiService.store('orden-produccion', formData)
      .pipe(this.untilDestroyed())
      .subscribe(
      (response) => {
        this.alertService.success(
          'Estado actualizado',
          'El estado de la orden de producción fue actualizado.'
        );
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  onFileSelected(event: any) {
    const file = event.target.files[0];
    if (file) {
      // Validar tipo de archivo
      if (file.type !== 'application/pdf') {
        this.alertService.error('Solo se permiten archivos PDF');
        event.target.value = '';
        return;
      }

      // Validar tamaño (5MB máximo)
      if (file.size > 5 * 1024 * 1024) {
        this.alertService.error('El archivo no debe superar los 5MB');
        event.target.value = '';
        return;
      }

      this.selectedFile = file;
    }
  }


  public verDocumento(event?: any) {
    if (event) {
      event.preventDefault(); // Prevenir cualquier acción por defecto
    }
    this.filtros = {
      id: this.orden.id,
    };
    this.downloading = true;
    this.apiService
      .export('ordenes-produccion/exportar/documento', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(
        (data: Blob) => {
          const blob = new Blob([data], {
            type: 'application/pdf',
          });
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download =  this.cotizacion.nombre_documento;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(url);
          this.downloading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.downloading = false;
        }
      );
  }
}
