import { Component, OnInit, TemplateRef, Output, Input, EventEmitter, ViewChild, OnChanges, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { SelectSearchComponent } from '@shared/parts/select-search/select-search.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { CrearClienteComponent } from '@shared/modals/crear-cliente/crear-cliente.component';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

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
export class CrearEventoComponent extends BaseModalComponent implements OnInit, OnChanges {

  @Input() evento: any = {};
  public productos: any = [];
  public clientes: any = [];
  public usuarios: any = [];
  public usuarioActual: any = {};
  public detalle: any = {};
  public productoSeleccionado: any = undefined;
  @Output() update = new EventEmitter();
  public override loading = false;
  public override saving: boolean = false;
  public sucursales: any = [];
  @ViewChild("createProductModal") createProductModal!: any;
  @ViewChild("eventosEnConflictoModal") eventosEnConflictoModal!: any;
  @ViewChild("conflictedEventModal") conflictedEventModal!: any;
  conflictedEvent: any;
  modalCreateProductRef: any;
  conflictEvents: any = [];
  eventosConflictoModalRef: any;

  constructor(
    private apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService
  ) {
    super(modalManager, alertService);
  }

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

      if (evento.inicio) {
        evento.inicio = this.aDatetimeLocal(evento.inicio);
      }
      if (evento.fin) {
        evento.fin = this.aDatetimeLocal(evento.fin);
      }
      this.corregirFinDeMadrugada(evento);
      this.normalizarSelects(evento);

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
    const fecha = moment(this.evento.inicio);
    const duraciones: Record<string, [number, 'minutes' | 'hour']> = {
      '15 minutos': [15, 'minutes'],
      '30 minutos': [30, 'minutes'],
      '1 hora': [1, 'hour'],
      '2 horas': [2, 'hour'],
      '3 horas': [3, 'hour'],
      '5 horas': [5, 'hour'],
      '8 horas': [8, 'hour'],
      '12 horas': [12, 'hour'],
    };
    const duracion = duraciones[this.evento.duracion];
    if (!duracion || !fecha.isValid()) {
      return;
    }
    this.evento.fin = fecha.clone().add(duracion[0], duracion[1]).format('YYYY-MM-DDTHH:mm');
  }

  private aDatetimeLocal(value: string): string {
    return String(value).replace(' ', 'T').replace(/\.\d+/, '').replace(/Z$/, '').substring(0, 16);
  }

  private corregirFinDeMadrugada(evento: any) {
    const inicio = new Date(evento.inicio);
    const fin = new Date(evento.fin);
    if (Number.isNaN(inicio.getTime()) || Number.isNaN(fin.getTime()) || fin >= inicio) {
      return;
    }
    const mismoDia = inicio.getFullYear() === fin.getFullYear()
      && inicio.getMonth() === fin.getMonth()
      && inicio.getDate() === fin.getDate();
    if (!mismoDia) {
      return;
    }
    fin.setDate(fin.getDate() + 1);
    const pad = (n: number) => String(n).padStart(2, '0');
    evento.fin = `${fin.getFullYear()}-${pad(fin.getMonth() + 1)}-${pad(fin.getDate())}T${pad(fin.getHours())}:${pad(fin.getMinutes())}`;
  }

  private normalizarSelects(evento: any) {
    const repeticiones = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];
    if (!repeticiones.includes(evento.frecuencia)) {
      evento.frecuencia = '';
      evento.frecuencia_fin = null;
    }

    const opciones = ['15 minutos', '30 minutos', '1 hora', '2 horas', '3 horas', '5 horas', '8 horas', '12 horas'];
    if (opciones.includes(evento.duracion)) {
      return;
    }

    const porNumero: Record<string, string> = {
      '15': '15 minutos',
      '30': '30 minutos',
      '1': '1 hora',
      '2': '2 horas',
      '3': '3 horas',
      '5': '5 horas',
      '8': '8 horas',
      '12': '12 horas',
    };
    const numero = String(Number(evento.duracion));
    if (porNumero[numero]) {
      evento.duracion = porNumero[numero];
      return;
    }

    const inicio = new Date(evento.inicio);
    const fin = new Date(evento.fin);
    const minutos = Math.round((fin.getTime() - inicio.getTime()) / 60000);
    const porMinutos: Record<number, string> = {
      15: '15 minutos', 30: '30 minutos', 60: '1 hora', 120: '2 horas',
      180: '3 horas', 300: '5 horas', 480: '8 horas', 720: '12 horas',
    };
    if (porMinutos[minutos]) {
      evento.duracion = porMinutos[minutos];
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

    if (!['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'].includes(eventoParaEnviar.frecuencia)) {
      eventoParaEnviar.frecuencia = null;
      delete eventoParaEnviar.frecuencia_fin;
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
        this.closeModal();
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
    if (this.eventosConflictoModalRef) {
      this.modalManager.closeModal(this.eventosConflictoModalRef);
      this.eventosConflictoModalRef = undefined;
    }
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
    this.modalCreateProductRef = this.modalManager.openModal(this.createProductModal, { backdrop: 'static' });
  }
  onProductoCreated(producto: any) {
    this.productos.unshift(producto);
    this.productoSeleccionado = producto;
    this.agregarDetalle();
  }
  public openModalConflicto(template: TemplateRef<any>, evento: any) {
    this.conflictedEvent = evento;
    this.eventosConflictoModalRef = this.modalManager.openModal(template, { class: 'modal-lg', backdrop: 'static' });
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
