import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-retaceos-list',
  templateUrl: './retaceos-list.component.html'
})
export class RetaceosListComponent implements OnInit {

  public retaceos: any[] = [];
  public loading = false;
  
  public filtros: any = {
    fecha_desde: '',
    fecha_hasta: '',
    busqueda: '',
    pagina: 1,
    limite: 10
  };
  
  public paginacion: any = {
    pagina_actual: 1,
    total_paginas: 0,
    total: 0,
    desde: 0,
    hasta: 0
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router
  ) { }

  ngOnInit() {
    // Inicializar fechas por defecto (último mes)
    const hoy = new Date();
    const mesAnterior = new Date();
    mesAnterior.setMonth(hoy.getMonth() - 1);
    
    this.filtros.fecha_desde = this.formatDate(mesAnterior);
    this.filtros.fecha_hasta = this.formatDate(hoy);
    
    this.cargarRetaceos();
  }

  formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  cargarRetaceos() {
    this.loading = true;
    
    this.apiService.getAll('retaceos', this.filtros).subscribe(response => {
      this.retaceos = response.data;
      
      // Cargar información adicional de compras para cada retaceo
      this.retaceos.forEach(retaceo => {
        if (retaceo.id_compra) {
          this.apiService.read('compra/', retaceo.id_compra).subscribe(compra => {
            retaceo.compra = compra;
          });
        }
      });
      
      // Actualizar paginación
      this.paginacion = {
        pagina_actual: response.current_page,
        total_paginas: response.last_page,
        total: response.total,
        desde: response.from,
        hasta: response.to
      };
      
      this.loading = false;
    }, error => {
      this.alertService.error(error);
      this.loading = false;
    });
  }

  buscar() {
    this.filtros.pagina = 1;
    this.cargarRetaceos();
  }

  limpiarFiltros() {
    const hoy = new Date();
    const mesAnterior = new Date();
    mesAnterior.setMonth(hoy.getMonth() - 1);
    
    this.filtros = {
      fecha_desde: this.formatDate(mesAnterior),
      fecha_hasta: this.formatDate(hoy),
      busqueda: '',
      pagina: 1,
      limite: 10
    };
    
    this.cargarRetaceos();
  }

  cambiarPagina(pagina: number) {
    if (pagina < 1 || pagina > this.paginacion.total_paginas) {
      return;
    }
    
    this.filtros.pagina = pagina;
    this.cargarRetaceos();
  }

  obtenerPaginas(): number[] {
    const paginas: number[] = [];
    const actual = this.paginacion.pagina_actual;
    const total = this.paginacion.total_paginas;
    
    // Mostrar máximo 5 páginas
    const inicio = Math.max(1, actual - 2);
    const fin = Math.min(total, inicio + 4);
    
    for (let i = inicio; i <= fin; i++) {
      paginas.push(i);
    }
    
    return paginas;
  }

  verRetaceo(retaceo: any) {
    this.router.navigate(['/retaceo', retaceo.id]);
  }

  eliminarRetaceo(id: number) {
    Swal.fire({
      title: '¿Está seguro?',
      text: 'Esta acción no se puede revertir. Los costos de los productos volverán a su valor original.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then(result => {
      if (result.isConfirmed) {
        this.apiService.delete('retaceo/', id).subscribe(() => {
          this.alertService.success('Retaceo eliminado correctamente', 'Retaceo eliminado');
          this.cargarRetaceos();
        }, error => {
          this.alertService.error(error);
        });
      }
    });
  }
}