import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  AlertasCatalogoCategoria,
  AlertasCatalogoItem,
  AlertasResumenPeriodico,
  AlertasVistaPreviaMensaje,
} from './alertas-resumenes.model';
import { ALERTAS_CATALOGO_MOCK } from './alertas-catalogo.mock';
import { ALERTAS_RESUMENES_CONFIG_MOCK } from './alertas-resumenes.mock';

@Component({
  selector: 'app-alertas-resumenes',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './alertas-resumenes.component.html',
  styleUrls: ['./alertas-resumenes.component.css'],
})
export class AlertasResumenesComponent {
  readonly config = ALERTAS_RESUMENES_CONFIG_MOCK;
  resumenes: AlertasResumenPeriodico[] = this.config.resumenesPeriodicos.map((r) => ({
    ...r,
  }));

  vistaPeriodo: 'semana' | 'mes' = 'semana';
  vistaCanal: 'whatsapp' | 'correo' = 'whatsapp';

  catalogo: AlertasCatalogoCategoria[] = ALERTAS_CATALOGO_MOCK.map((cat) => ({
    ...cat,
    alertas: cat.alertas.map((a) => ({ ...a })),
  }));
  catalogoExpandido = new Set<string>(
    ALERTAS_CATALOGO_MOCK.flatMap((c) =>
      c.alertas.filter((a) => a.expandidoInicial).map((a) => a.id),
    ),
  );

  abrirVistaPrevia(id: 'semana' | 'mes'): void {
    this.vistaPeriodo = id;
  }

  toggleCanal(resumen: AlertasResumenPeriodico, canal: 'whatsapp' | 'correo'): void {
    if (canal === 'whatsapp') {
      resumen.whatsapp = !resumen.whatsapp;
    } else {
      resumen.correo = !resumen.correo;
    }
  }

  mensajePrevia(): AlertasVistaPreviaMensaje {
    return this.vistaPeriodo === 'mes'
      ? this.config.vistaPrevia.mensual
      : this.config.vistaPrevia.semanal;
  }

  toggleCatalogoExpand(id: string): void {
    if (this.catalogoExpandido.has(id)) {
      this.catalogoExpandido.delete(id);
    } else {
      this.catalogoExpandido.add(id);
    }
  }

  catalogoExpandidoEs(id: string): boolean {
    return this.catalogoExpandido.has(id);
  }

  toggleCatalogoCanal(alerta: AlertasCatalogoItem, canal: 'whatsapp' | 'correo'): void {
    if (!alerta.activo) {
      return;
    }
    if (canal === 'whatsapp') {
      alerta.whatsapp = !alerta.whatsapp;
    } else {
      alerta.correo = !alerta.correo;
    }
  }

  onCatalogoActivoChange(alerta: AlertasCatalogoItem): void {
    if (!alerta.activo) {
      alerta.estado = 'apagada';
    } else if (alerta.estado === 'apagada') {
      alerta.estado = 'sin_novedad';
    }
  }

  etiquetaEstado(estado: AlertasCatalogoItem['estado']): string {
    if (estado === 'enviaria_hoy') {
      return 'se enviaría hoy';
    }
    if (estado === 'apagada') {
      return 'apagada';
    }
    return 'sin novedad';
  }
}
