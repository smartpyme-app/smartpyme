import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { FidelizacionService } from '@services/fidelizacion.service';
import { ApiService } from '@services/api.service';
import { 
  TipoClienteEmpresa, 
  PaginatedResponse
} from '../../../models/fidelizacion.interface';
import { 
  ClienteDetalles,
  HistorialPunto,
  Beneficio
} from '../../../services/fidelizacion.service';

@Component({
  selector: 'app-cliente-detalles-fidelizacion',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './cliente-detalles-fidelizacion.component.html',
  styleUrls: ['./cliente-detalles-fidelizacion.component.css']
})
export class ClienteDetallesFidelizacionComponent implements OnInit {

  public cliente: ClienteDetalles | null = null;
  public historialPuntos: HistorialPunto[] = [];
  public beneficios: Beneficio[] = [];
  public loading: boolean = false;
  public activeTab: string = 'recent';

  constructor(
    private fidelizacionService: FidelizacionService,
    private apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router
  ) { }

  ngOnInit(): void {
    this.route.params.subscribe(params => {
      if (params['id']) {
        this.loadClienteDetalle(params['id']);
      }
    });
  }

  /**
   * Cargar detalles del cliente
   */
  loadClienteDetalle(clienteId: number): void {
    this.loading = true;
    
    this.fidelizacionService.getClienteDetalles(clienteId).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.cliente = response.data;
          this.loadHistorialPuntos(clienteId);
          this.loadBeneficios(clienteId);
        } else {
          this.alertService.error(response.message || 'Error al cargar los detalles del cliente');
        }
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error('Error al cargar los detalles del cliente');
        this.loading = false;
      }
    });
  }

  /**
   * Cargar historial de puntos
   */
  loadHistorialPuntos(clienteId: number): void {
    this.fidelizacionService.getHistorialPuntos(clienteId, { paginate: 50 }).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.historialPuntos = response.data.data;
        }
      },
      error: (error) => {
        console.error('Error al cargar historial de puntos:', error);
      }
    });
  }

  /**
   * Cargar beneficios disponibles
   */
  loadBeneficios(clienteId: number): void {
    this.fidelizacionService.getBeneficiosDisponibles(clienteId).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.beneficios = response.data;
        }
      },
      error: (error) => {
        console.error('Error al cargar beneficios:', error);
      }
    });
  }


  /**
   * Cambiar tab activo
   */
  setActiveTab(tab: string): void {
    this.activeTab = tab;
  }

  /**
   * Obtener nombre del nivel
   */
  getNivelNombre(nivel: number): string {
    return this.fidelizacionService.getNivelNombre(nivel);
  }

  /**
   * Obtener clase CSS para el nivel
   */
  getNivelClass(nivel: number): string {
    return this.fidelizacionService.getNivelClass(nivel);
  }

  /**
   * Obtener tipo de cliente actual
   */
  getTipoClienteActual(): string {
    if (this.cliente?.tipo_cliente_fidelizacion) {
      return this.cliente.tipo_cliente_fidelizacion.nombre_efectivo;
    }
    return 'Sin tipo asignado';
  }

  /**
   * Formatear fecha
   */
  formatDate(dateString: string): string {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES');
  }

  /**
   * Formatear número con separadores de miles
   */
  formatNumber(num: number): string {
    if (!num) return '0';
    return num.toLocaleString('es-ES');
  }

  /**
   * Formatear moneda
   */
  formatCurrency(amount: number): string {
    if (!amount) return '$0.00';
    return new Intl.NumberFormat('es-ES', {
      style: 'currency',
      currency: 'USD'
    }).format(amount);
  }

  /**
   * Obtener clase CSS para el tipo de punto
   */
  getPuntoClass(tipo: string): string {
    switch (tipo) {
      case 'ganado':
        return 'text-success';
      case 'canjeado':
        return 'text-warning';
      case 'vencido':
        return 'text-danger';
      default:
        return 'text-muted';
    }
  }

  /**
   * Obtener icono para el tipo de punto
   */
  getPuntoIcon(tipo: string): string {
    switch (tipo) {
      case 'ganado':
        return 'fa-plus-circle';
      case 'canjeado':
        return 'fa-minus-circle';
      case 'vencido':
        return 'fa-times-circle';
      default:
        return 'fa-circle';
    }
  }

  /**
   * Calcular progreso hacia el siguiente nivel
   */
  getProgresoNivel(): number {
    if (!this.cliente) return 0;
    
    const puntosActuales = this.cliente.puntos_disponibles || 0;
    const puntosSiguienteNivel = this.getPuntosSiguienteNivel();
    
    if (puntosSiguienteNivel === 0) return 100;
    
    return Math.min((puntosActuales / puntosSiguienteNivel) * 100, 100);
  }

  /**
   * Obtener puntos necesarios para el siguiente nivel
   */
  getPuntosSiguienteNivel(): number {
    if (!this.cliente?.tipo_cliente_fidelizacion) return 0;
    
    const tipoCliente = this.cliente.tipo_cliente_fidelizacion;
    const nivelActual = this.cliente.nivel_actual || 1;
    
    // Si tiene configuración avanzada con reglas de upgrade, usarla
    if (tipoCliente.configuracion_avanzada?.upgrade_automatico?.reglas) {
      const reglas = tipoCliente.configuracion_avanzada.upgrade_automatico.reglas;
      
      // Buscar la regla de puntos acumulados para el siguiente nivel
      const reglaPuntos = reglas.find((regla: any) => 
        regla.tipo === 'puntos_acumulados' && 
        regla.nivel_destino === nivelActual + 1 && 
        regla.activo
      );
      
      if (reglaPuntos && reglaPuntos.umbral) {
        return reglaPuntos.umbral;
      }
      
      // Si no hay regla de puntos, buscar cualquier regla para el siguiente nivel
      const reglaCualquiera = reglas.find((regla: any) => 
        regla.nivel_destino === nivelActual + 1 && 
        regla.activo
      );
      
      if (reglaCualquiera && reglaCualquiera.umbral) {
        return reglaCualquiera.umbral;
      }
    }
    
    // Si no hay reglas o no se encuentra ninguna, retornar 0
    return 0;
  }

  /**
   * Obtener progreso hacia el siguiente nivel (porcentaje)
   */
  getProgressToNextLevel(): number {
    if (!this.cliente) return 0;
    
    const puntosActuales = this.cliente.puntos_disponibles || 0;
    const puntosSiguienteNivel = this.getPuntosSiguienteNivel();
    
    if (puntosSiguienteNivel === 0) return 100;
    
    return Math.min((puntosActuales / puntosSiguienteNivel) * 100, 100);
  }

  /**
   * Obtener puntos necesarios para el siguiente nivel
   */
  getPointsToNextLevel(): number {
    if (!this.cliente) return 0;
    
    const puntosActuales = this.cliente.puntos_disponibles || 0;
    const puntosSiguienteNivel = this.getPuntosSiguienteNivel();
    
    if (puntosSiguienteNivel === 0) return 0;
    
    return Math.max(puntosSiguienteNivel - puntosActuales, 0);
  }

  /**
   * Obtener nombre del siguiente nivel
   */
  getNextLevelName(): string {
    if (!this.cliente?.tipo_cliente_fidelizacion) return '';
    
    const tipoCliente = this.cliente.tipo_cliente_fidelizacion;
    const nivelActual = this.cliente.nivel_actual || 1;
    
    // Si tiene configuración avanzada con reglas de upgrade, usarla
    if (tipoCliente.configuracion_avanzada?.upgrade_automatico?.reglas) {
      const reglas = tipoCliente.configuracion_avanzada.upgrade_automatico.reglas;
      
      // Buscar la regla que corresponde al siguiente nivel
      const reglaActual = reglas.find((regla: any) => 
        regla.nivel_destino === nivelActual + 1 && regla.activo
      );
      
      if (reglaActual && reglaActual.descripcion) {
        // Extraer el nombre del nivel de la descripción
        // Ej: "Upgrade a VIP por 800+ puntos acumulados" -> "VIP"
        const match = reglaActual.descripcion.match(/Upgrade a (\w+)/);
        if (match) {
          return match[1];
        }
        return reglaActual.descripcion;
      }
    }
    
    // Si no hay reglas o no se encuentra ninguna, retornar mensaje apropiado
    return 'Nivel Máximo';
  }


  /**
   * Obtener el valor de un punto según el tipo de cliente
   */
  getPuntoValor(): number {
    if (!this.cliente?.tipo_cliente_fidelizacion) return 1.0;
    return 1 / (this.cliente.tipo_cliente_fidelizacion.puntos_por_dolar || 1.0);
  }

  /**
   * Obtener puntos por dólar (valor directo del backend)
   */
  getPuntosPorDolar(): number {
    if (!this.cliente?.tipo_cliente_fidelizacion) return 1.0;
    return this.cliente.tipo_cliente_fidelizacion.puntos_por_dolar || 1.0;
  }

  /**
   * Obtener meses de expiración de puntos
   */
  getExpiracionMeses(): number {
    if (!this.cliente?.tipo_cliente_fidelizacion) return 12;
    return this.cliente.tipo_cliente_fidelizacion.expiracion_meses || 12;
  }

  /**
   * Obtener reglas de upgrade del tipo de cliente
   */
  getReglasUpgrade(): any[] {
    if (!this.cliente?.tipo_cliente_fidelizacion?.configuracion_avanzada?.upgrade_automatico?.reglas) {
      return [];
    }
    return this.cliente.tipo_cliente_fidelizacion.configuracion_avanzada.upgrade_automatico.reglas.filter((regla: any) => regla.activo);
  }

  /**
   * Obtener beneficios especiales del tipo de cliente
   */
  getBeneficiosEspeciales(): string[] {
    if (!this.cliente?.tipo_cliente_fidelizacion?.configuracion_avanzada) {
      return [];
    }

    const config = this.cliente.tipo_cliente_fidelizacion.configuracion_avanzada;
    const beneficios: string[] = [];

    // Solo agregar beneficios que realmente existen según la configuración del seeder
    if (config.multiplicador_especial === true && config.multiplicador_valor && config.multiplicador_valor > 0) {
      beneficios.push(`Multiplicador especial x${config.multiplicador_valor}`);
    }

    if (config.descuento_cumpleanos === true && config.descuento_cumpleanos_porcentaje && config.descuento_cumpleanos_porcentaje > 0) {
      beneficios.push(`Descuento cumpleaños ${config.descuento_cumpleanos_porcentaje}%`);
    }

    if (config.acceso_exclusivo === true) {
      beneficios.push('Acceso exclusivo');
    }

    if (config.soporte_prioritario === true) {
      beneficios.push('Soporte prioritario');
    }

    // TODO: BENEFICIOS_EXCLUSIVOS - Descomentar cuando el backend implemente la lógica
    // if (config.beneficios_exclusivos && typeof config.beneficios_exclusivos === 'object') {
    //   const exclusivos = config.beneficios_exclusivos;
    //   if (exclusivos.descuento_maximo_adicional && exclusivos.descuento_maximo_adicional > 0) {
    //     beneficios.push(`Descuento adicional ${exclusivos.descuento_maximo_adicional}%`);
    //   }
    //   if (exclusivos.puntos_bienvenida_anual && exclusivos.puntos_bienvenida_anual > 0) {
    //     beneficios.push(`${exclusivos.puntos_bienvenida_anual} puntos de bienvenida anual`);
    //   }
    //   if (exclusivos.acceso_eventos_vip === true) beneficios.push('Acceso a eventos VIP');
    //   if (exclusivos.entrega_express_gratis === true) beneficios.push('Entrega express gratis');
    //   if (exclusivos.asistente_personal === true) beneficios.push('Asistente personal');
    // }

    return beneficios;
  }



  /**
   * Ver historial de transacciones
   */
  viewTransactionHistory(): void {
    this.setActiveTab('history');
  }



  /**
   * Cambiar tipo de cliente
   */
  changeClientType(): void {
    this.alertService.info('Info', 'Función de cambio de tipo en desarrollo');
  }

  /**
   * Volver a la lista de clientes
   */
  volver(): void {
    this.router.navigate(['/fidelizacion/clientes']);
  }

  /**
   * Enviar correo electrónico al cliente
   */
  enviarCorreo(): void {
    if (this.cliente?.correo) {
      window.open(`mailto:${this.cliente.correo}`, '_blank');
    }
  }

  /**
   * Enviar WhatsApp al cliente
   */
  enviarWhatsApp(): void {
    if (this.cliente?.telefono) {
      // Limpiar el número de teléfono, solo números
      const telefonoLimpio = this.cliente.telefono.replace(/[^0-9]/g, '');
      // Agregar código de país si no lo tiene (El Salvador es 503)
      const telefonoCompleto = telefonoLimpio.startsWith('503') ? telefonoLimpio : `503${telefonoLimpio}`;
      window.open(`https://wa.me/${telefonoCompleto}`, '_blank');
    }
  }
}
