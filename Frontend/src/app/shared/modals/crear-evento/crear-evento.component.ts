import { Component, OnInit, TemplateRef, Output, Input, EventEmitter, ViewChild, OnChanges, SimpleChanges, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';
import { SelectSearchComponent } from '@shared/parts/select-search/select-search.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { CrearClienteComponent } from '@shared/modals/crear-cliente/crear-cliente.component';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import * as moment from 'moment';
import { CrearProductoComponent } from '../crear-producto/crear-producto.component';
import Swal from 'sweetalert2';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';

@Component({
    selector: 'app-crear-evento',
    templateUrl: './crear-evento.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, SelectSearchComponent, NotificacionesContainerComponent, CrearClienteComponent, NgSelectModule],
    
})
export class CrearEventoComponent implements OnInit, OnChanges {

  @Input() evento: any = {};
  public productos: any = [];
  public clientes: any = [];
  public usuarios: any = [];
  public usuarioActual: any = {};
  public detalle: any = {};
  public productoSeleccionado: any = undefined;
  @Output() update = new EventEmitter();
  public loading = false;
  public saving: boolean = false;
  public sucursales: any = [];
  modalRef?: BsModalRef;
  @ViewChild("createProductModal") createProductModal!: any;
  @ViewChild("eventosEnConflictoModal") eventosEnConflictoModal!: any;
  @ViewChild("conflictedEventModal") conflictedEventModal!: any;
  conflictedEvent: any;
  modalCreateProductRef?: BsModalRef;
  conflictEvents: any = [];
  eventosConflictoModalRef?: BsModalRef;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private apiService: ApiService, private alertService: AlertService,
    private modalService: BsModalService
  ) { }

  ngOnInit() {

    this.apiService.getAll('usuarios/list')
      .pipe(this.untilDestroyed())
      .subscribe(usuarios => {
      this.usuarios = usuarios;
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('clientes/list')
      .pipe(this.untilDestroyed())
      .subscribe(clientes => {
      this.clientes = clientes;
      // Si estamos editando un evento y tiene un cliente asignado, 
      // asegurarnos de que el cliente esté en la lista para que se muestre correctamente
      if (this.evento && this.evento.id_cliente) {
        const clienteExistente = this.clientes.find((c: any) => c.id === this.evento.id_cliente);
        if (!clienteExistente && this.evento.cliente) {
          // Si el cliente no está en la lista pero viene en el evento, agregarlo
          this.clientes.push(this.evento.cliente);
        } else if (!clienteExistente) {
          // Si el cliente no está en la lista, intentar cargarlo individualmente
          this.apiService.read('clientes/', this.evento.id_cliente)
            .pipe(this.untilDestroyed())
            .subscribe((cliente: any) => {
            if (cliente) {
              this.clientes.push(cliente);
            }
          }, (error: any) => {
            console.error('Error cargando cliente:', error);
          });
        }
      }
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('sucursales/list')
      .pipe(this.untilDestroyed())
      .subscribe(sucursales => {
      this.sucursales = sucursales;
    }, error => { this.alertService.error(error); });

    this.usuarioActual = this.apiService.auth_user();
    console.log(this.usuarioActual);
    
    if (this.isCitas()) {
      this.evento.id_usuario = this.usuarioActual.id;
    }

  }

  ngOnChanges(changes: SimpleChanges) {
    // Cuando cambia el evento (al editar), asegurarse de que los productos tengan los datos correctos
    if (changes['evento'] && changes['evento'].currentValue) {
      const evento = changes['evento'].currentValue;
      console.log('Evento recibido en ngOnChanges:', evento);
      
      // Convertir fechas del formato backend (YYYY-MM-DD HH:mm:ss) a datetime-local (YYYY-MM-DDTHH:mm)
      if (evento.inicio && evento.inicio.includes(' ') && !evento.inicio.includes('T')) {
        evento.inicio = evento.inicio.replace(' ', 'T').substring(0, 16);
      }
      if (evento.fin && evento.fin.includes(' ') && !evento.fin.includes('T')) {
        evento.fin = evento.fin.replace(' ', 'T').substring(0, 16);
      }
      
      // Debug productos
      if (evento.productos && evento.productos.length > 0) {
        console.log('Productos del evento:', evento.productos);
        evento.productos.forEach((producto: any, index: number) => {
          console.log(`Producto ${index}:`, {
            id: producto.id,
            nombre_producto: producto.nombre_producto,
            precio_producto: producto.precio_producto,
            cantidad: producto.cantidad,
            total_calculado: (producto.precio_producto || 0) * (producto.cantidad || 0)
          });
        });
        
        // Asegurar que los productos tengan los datos correctos
        this.ensureProductosData(evento.productos);
      }
      
      if (evento.id_cliente && this.clientes.length > 0) {
        const clienteExistente = this.clientes.find((c: any) => c.id === evento.id_cliente);
        if (!clienteExistente && evento.cliente) {
          // Si el cliente no está en la lista pero viene en el evento, agregarlo
          this.clientes.push(evento.cliente);
        } else if (!clienteExistente) {
          // Si el cliente no está en la lista, intentar cargarlo individualmente
          this.apiService.read('clientes/', evento.id_cliente)
            .pipe(this.untilDestroyed())
            .subscribe((cliente: any) => {
            if (cliente) {
              this.clientes.push(cliente);
            }
          }, (error: any) => {
            console.error('Error cargando cliente:', error);
          });
        }
      }
    }
  }



  setTipo() {
    if (this.evento.tipo = 'Confirmado') {
      this.evento.tipo = 'Sin confirmar';
    } else {
      this.evento.tipo = 'Confirmado';
    }
  }

  isCitas() {
    return this.usuarioActual.tipo === 'Citas';
  }


  setTime() {
    let fecha = moment(this.evento.inicio);

    if (this.evento.duracion == '15 minutos') {
      this.evento.fin = fecha.add(15, 'minutes').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '30 minutos') {
      this.evento.fin = fecha.add(30, 'minutes').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '1 hora') {
      this.evento.fin = fecha.add(1, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '2 horas') {
      this.evento.fin = fecha.add(2, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '3 horas') {
      this.evento.fin = fecha.add(3, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '5 horas') {
      this.evento.fin = fecha.add(5, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '8 horas') {
      this.evento.fin = fecha.add(8, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '12 horas') {
      this.evento.fin = fecha.add(12, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
  }

  setFrecuenciaFin() {
    let fecha = moment(this.evento.inicio);

    if (!this.evento.veces) {
      this.evento.veces = 1;
    }

    if (this.evento.frecuencia == "DAILY") {
      this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'day').format('YYYY-MM-DD');
    }
    if (this.evento.frecuencia == "WEEKLY") {
      this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'week').format('YYYY-MM-DD');
    }
    if (this.evento.frecuencia == "MONTHLY") {
      this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'month').format('YYYY-MM-DD');
    }
    if (this.evento.frecuencia == "YEARLY") {
      this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'year').format('YYYY-MM-DD');
    }
  }

  // Cliente
  public setCliente(cliente: any) {
    if (!this.evento.id_cliente) {
      this.clientes.push(cliente);
    }
    this.evento.id_cliente = cliente.id;
  }

  public onSubmit() {
    this.saving = true;
    
    // Preparar el evento para enviar, convirtiendo fechas de datetime-local a formato backend
    const eventoParaEnviar = { ...this.evento };
    
    // Convertir inicio de datetime-local (YYYY-MM-DDTHH:mm) a formato backend (YYYY-MM-DD HH:mm:ss)
    if (eventoParaEnviar.inicio) {
      if (eventoParaEnviar.inicio.includes('T')) {
        eventoParaEnviar.inicio = eventoParaEnviar.inicio.replace('T', ' ') + ':00';
      }
    }
    
    // Convertir fin de datetime-local a formato backend
    if (eventoParaEnviar.fin) {
      if (eventoParaEnviar.fin.includes('T')) {
        eventoParaEnviar.fin = eventoParaEnviar.fin.replace('T', ' ') + ':00';
      }
    }
    
    this.apiService.store('evento', eventoParaEnviar)
      .pipe(this.untilDestroyed())
      .subscribe(evento => {
      if (!this.evento.id) {
        this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
      } else {
        this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
      }
      this.update.emit();
      this.saving = false;
      this.modalRef?.hide();
    }, error => {
      if (error.error.errorType == "event_conflict") {
        this.conflictEvents = error.error.conflicts;
      }
      else {
        this.alertService.error(error); this.saving = false;
      }
    });
  }

  ObSubmitConflicted() {
    this.onSubmit();
    this.eventosConflictoModalRef?.hide();

  }
  public agregarDetalle() {

    if (this.productoSeleccionado?.id) {
      this.detalle.nombre_producto = this.productoSeleccionado.nombre;
      this.detalle.id_producto = this.productoSeleccionado.id;
      this.detalle.cantidad = 1;
      this.detalle.id_evento = this.evento.id;
      // Agregar precio_producto para que se muestre inmediatamente
      this.detalle.precio_producto = this.productoSeleccionado.precio;

      let detalle = Object.assign({}, this.detalle);
      if (!this.evento.productos) this.evento.productos = [];

      this.evento.productos.unshift(detalle);
      this.detalle = {};
    }
    this.productoSeleccionado = null;

  }

  public eliminarDetalle(index: number) {
    this.evento.productos.splice(index, 1);
  }

  public recalcularTotal(producto: any) {
    // El total se calcula automáticamente en el template
    // Este método se mantiene por si necesitamos lógica adicional en el futuro
    console.log('Recalculando total para producto:', producto.nombre_producto);
  }

  // Función para asegurar que los productos tengan los datos correctos
  private ensureProductosData(productos: any[]) {
    productos.forEach((producto: any) => {
      // Si el producto no tiene precio_producto, intentar cargarlo del producto original
      if (!producto.precio_producto) {
        this.apiService.read('productos/', producto.id_producto)
          .pipe(this.untilDestroyed())
          .subscribe((productoOriginal: any) => {
          if (productoOriginal) {
            producto.precio_producto = productoOriginal.precio;
            console.log('Producto actualizado con datos del original:', producto);
          }
        }, (error: any) => {
          console.error('Error cargando producto original:', error);
        });
      }
    });
  }
  crearProducto() {
    this.modalCreateProductRef = this.modalService.show(this.createProductModal, { backdrop: 'static' });
  }
  onProductoCreated(producto: any) {
    this.productos.unshift(producto);
    this.productoSeleccionado = producto;
    this.agregarDetalle();
  }
  public openModal(template: TemplateRef<any>, evento: any) {
    this.conflictedEvent = evento;

    this.alertService.modal = true;
    this.eventosConflictoModalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
  }

  public delete(evento: any) {

    Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminarlo',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.delete('evento/', evento.id)
          .pipe(this.untilDestroyed())
          .subscribe(data => {
          this.onSubmit();
        }, error => { this.alertService.error(error); });
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
      }
    });

  }
  public setEstado(evento: any, estado: any) {
    this.conflictedEvent = evento;
    this.conflictedEvent.tipo = estado;

    this.apiService.store('evento', this.conflictedEvent)
      .pipe(this.untilDestroyed())
      .subscribe(evento => {
      this.alertService.success('Cita actualizada', 'La cita fue actualida exitosamente.');
      this.ObSubmitConflicted();
      this.conflictedEvent = null;
    }, error => {
      this.alertService.error(error); this.saving = false;
    });
  }

  // Función de búsqueda para clientes en servidor, con manejo de errores y respuesta flexible
  searchClientes = (term: string): Observable<any[]> => {
    if (!term || term.length < 2) {
      return of([]); // No buscar si el término es muy corto
    }
    
    return this.apiService.getAll(`clientes/search?q=${encodeURIComponent(term)}`)
      .pipe(
        map((response: any) => {
          // Si la respuesta viene envuelta en un objeto data
          return Array.isArray(response) ? response : (response.data || []);
        }),
        catchError((error) => {
          console.error('Error buscando clientes:', error);
          this.alertService.error('Error al buscar clientes');
          return of([]); // Retornar array vacío en caso de error
        })
      );
  };

  // Función personalizada para mostrar clientes
  getClienteDisplay = (cliente: any): string => {
    return cliente.tipo === 'Persona' 
      ? cliente.nombre_completo 
      : cliente.nombre_empresa;
  };

  // Función para mostrar productos
  getProductoDisplay = (producto: any): string => {
    return `${producto.nombre} - ${producto.precio}`;
  };

  // Búsqueda de productos y servicios en servidor
  searchProductos = (term: string): Observable<any[]> => {
    if (!term || term.length < 2) {
      return of([]);
    }
    
    // Buscar tanto productos como servicios
    const tipos = ['Producto', 'Servicio'];
    const tiposParam = tipos.map(t => `tipos[]=${encodeURIComponent(t)}`).join('&');
    
    return this.apiService.getAll(`productos/search?q=${encodeURIComponent(term)}&limit=15&${tiposParam}`)
      .pipe(
        map((response: any) => Array.isArray(response) ? response : (response.data || [])),
        catchError(() => of([]))
      );
  };

  // Callbacks cuando cambia la selección
  onClienteChange(cliente: any) {
    console.log('Cliente seleccionado:', cliente);
    if (cliente) {
      // Puedes hacer lógica adicional aquí
      console.log('Datos del cliente:', cliente);
    }
  }

  onProductoChange(producto: any) {
    console.log('Producto seleccionado:', producto);
    this.productoSeleccionado = producto;
    // Automáticamente agregar el detalle cuando se selecciona un producto
    if (producto) {
      this.agregarDetalle();
    }
  }

  onUsuarioChange(usuario: any) {
    console.log('Usuario seleccionado:', usuario);
  }

  // Puedes agregar otros métodos o lógica aquí según sea necesario

}
