import { Component, Input, Output, EventEmitter, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { FidelizacionService, PuntosDisponiblesInfo } from '@services/fidelizacion.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-puntos-cliente-venta',
  templateUrl: './puntos-cliente-venta.component.html',
  styleUrls: ['./puntos-cliente-venta.component.css']
})
export class PuntosClienteVentaComponent implements OnInit, OnChanges {

  @Input() cliente: any = null;
  @Input() empresaId: number = 0;
  @Output() puntosCanjeados = new EventEmitter<{puntos: number, descuento: number}>();

  // Datos de puntos
  public puntosInfo: PuntosDisponiblesInfo | null = null;
  public loading: boolean = false;
  public mostrarPuntos: boolean = false;

  // Configuración del canje
  public usarPuntos: boolean = false;
  public puntosACanjear: number = 0;
  public valorPunto: number = 0.01; // Se actualizará con configuración del backend
  public minimoCanje: number = 1; // Se actualizará con configuración del backend
  public maximoCanje: number = 1000; // Se actualizará con configuración del backend
  
  // Estados
  public puntosProximosAExpirar: any[] = [];

  constructor(
    private fidelizacionService: FidelizacionService,
    private alertService: AlertService
  ) { }

  ngOnInit(): void {
    if (this.cliente && this.cliente.id && this.empresaId) {
      this.cargarPuntosCliente();
    }
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['cliente']) {
      if (this.cliente && this.cliente.id && this.empresaId) {
        this.cargarPuntosCliente();
      } else {
        this.resetearComponente();
      }
    }
  }

  /**
   * Cargar puntos disponibles del cliente
   */
  cargarPuntosCliente(): void {
    this.loading = true;
    this.fidelizacionService.getPuntosDisponiblesInfo(this.cliente.id, this.empresaId)
      .subscribe({
        next: (response) => {
          if (response.success && response.data) {
            this.puntosInfo = response.data;
            this.mostrarPuntos = this.puntosInfo.puntos_disponibles > 0;
            this.calcularPuntosProximosAExpirar();
            
            // Actualizar configuración desde el backend
            if (this.puntosInfo.configuracion) {
              this.valorPunto = this.puntosInfo.configuracion.valor_punto || 0.01;
              this.minimoCanje = this.puntosInfo.configuracion.minimo_canje || 1;
              this.maximoCanje = this.puntosInfo.configuracion.maximo_canje || 1000;
            }
          } else {
            this.mostrarPuntos = false;
            this.puntosInfo = null;
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Error al cargar puntos del cliente:', error);
          this.mostrarPuntos = false;
          this.loading = false;
        }
      });
  }

  /**
   * Calcular puntos próximos a expirar (dentro de 30 días)
   */
  calcularPuntosProximosAExpirar(): void {
    if (!this.puntosInfo) return;
    
    this.puntosProximosAExpirar = this.puntosInfo.ganancias_detalle
      .filter(ganancia => ganancia.dias_para_expirar <= 30 && ganancia.dias_para_expirar >= 0)
      .slice(0, 3); // Solo mostrar las 3 más próximas
  }

  /**
   * Manejar cambio en el checkbox de usar puntos
   */
  onToggleUsarPuntos(): void {
    if (!this.usarPuntos) {
      this.puntosACanjear = 0;
      this.emitirCambio();
    }
  }

  /**
   * Manejar cambio en la cantidad de puntos a canjear
   */
  onCambiarPuntosACanjear(): void {
    // Validaciones
    if (this.puntosACanjear < 0) {
      this.puntosACanjear = 0;
    }
    
    if (this.puntosInfo && this.puntosACanjear > this.puntosInfo.puntos_disponibles) {
      this.puntosACanjear = this.puntosInfo.puntos_disponibles;
      this.alertService.warning('Puntos insuficientes', `Solo tienes ${this.puntosInfo.puntos_disponibles} puntos disponibles`);
    }

    if (this.puntosACanjear > this.maximoCanje) {
      this.puntosACanjear = this.maximoCanje;
      this.alertService.warning('Límite excedido', `El máximo de canje es ${this.maximoCanje} puntos`);
    }

    this.emitirCambio();
  }

  /**
   * Usar todos los puntos disponibles
   */
  usarTodosPuntos(): void {
    if (this.puntosInfo) {
      this.puntosACanjear = this.puntosInfo.puntos_disponibles;
      this.usarPuntos = true;
      this.emitirCambio();
    }
  }

  /**
   * Emitir cambio a componente padre
   */
  private emitirCambio(): void {
    const descuento = this.puntosACanjear * this.valorPunto;
    this.puntosCanjeados.emit({
      puntos: this.usarPuntos ? this.puntosACanjear : 0,
      descuento: this.usarPuntos ? descuento : 0
    });
  }

  /**
   * Resetear el componente
   */
  resetearComponente(): void {
    this.puntosInfo = null;
    this.mostrarPuntos = false;
    this.usarPuntos = false;
    this.puntosACanjear = 0;
    this.puntosProximosAExpirar = [];
    this.emitirCambio();
  }

  /**
   * Formatear números
   */
  formatNumber(num: number): string {
    return new Intl.NumberFormat('es-ES').format(num);
  }

  /**
   * Formatear fechas
   */
  formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('es-ES', {
      month: 'short',
      day: 'numeric'
    });
  }

  /**
   * Obtener clase CSS para días de expiración
   */
  getDiasExpiracionClass(dias: number): string {
    if (dias <= 7) return 'text-danger fw-bold';
    if (dias <= 15) return 'text-warning';
    return 'text-info';
  }

  /**
   * Obtener texto de urgencia
   */
  getUrgenciaText(dias: number): string {
    if (dias <= 0) return '¡Expirados!';
    if (dias <= 7) return `¡${dias}d!`;
    return `${dias}d`;
  }

  /**
   * Calcular descuento total
   */
  getDescuentoTotal(): number {
    return this.puntosACanjear * this.valorPunto;
  }
}
