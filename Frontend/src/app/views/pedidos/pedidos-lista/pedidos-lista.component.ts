import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';

import { RestauranteService, PedidoCanal } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-pedidos-lista',
  templateUrl: './pedidos-lista.component.html',
  styleUrls: ['./pedidos-lista.component.css']
})
export class PedidosListaComponent implements OnInit {
  pedidos: PedidoCanal[] = [];
  loading = false;

  filtros: {
    estado: string;
    fecha_desde: string;
    fecha_hasta: string;
    canal: string;
  } = {
    estado: '',
    fecha_desde: '',
    fecha_hasta: '',
    canal: ''
  };

  constructor(
    private restauranteService: RestauranteService,
    private alertService: AlertService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros = {
      estado: '',
      fecha_desde: '',
      fecha_hasta: '',
      canal: ''
    };
    this.cargar();
  }

  cargar(): void {
    this.loading = true;
    const params: Record<string, string> = {};
    if (this.filtros.estado) {
      params['estado'] = this.filtros.estado;
    }
    if (this.filtros.fecha_desde) {
      params['fecha_desde'] = this.filtros.fecha_desde;
    }
    if (this.filtros.fecha_hasta) {
      params['fecha_hasta'] = this.filtros.fecha_hasta;
    }
    if (this.filtros.canal.trim()) {
      params['canal'] = this.filtros.canal.trim();
    }

    this.restauranteService.getPedidos(params).subscribe({
      next: (rows) => {
        this.pedidos = rows;
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      }
    });
  }

  nuevo(): void {
    this.router.navigate(['/pedidos/nuevo']);
  }

  editar(p: PedidoCanal): void {
    if (p.estado !== 'borrador') {
      return;
    }
    this.router.navigate(['/pedidos/editar', p.id]);
  }

  eliminar(p: PedidoCanal): void {
    if (p.estado !== 'borrador') {
      return;
    }
    if (!confirm('¿Eliminar este pedido?')) {
      return;
    }
    this.restauranteService.eliminarPedido(p.id).subscribe({
      next: () => {
        this.alertService.success('Pedido eliminado', '');
        this.cargar();
      },
      error: (err) => this.alertService.error(err)
    });
  }

  etiquetaEstado(estado: string): string {
    const m: Record<string, string> = {
      borrador: 'Borrador',
      pendiente_facturar: 'Pendiente de facturar',
      facturado: 'Facturado',
      anulado: 'Anulado'
    };
    return m[estado] || estado;
  }

  claseEstado(estado: string): string {
    if (estado === 'borrador') {
      return 'bg-secondary';
    }
    if (estado === 'pendiente_facturar') {
      return 'bg-warning text-dark';
    }
    if (estado === 'facturado') {
      return 'bg-success';
    }
    if (estado === 'anulado') {
      return 'bg-danger';
    }
    return 'bg-light text-dark';
  }
}
