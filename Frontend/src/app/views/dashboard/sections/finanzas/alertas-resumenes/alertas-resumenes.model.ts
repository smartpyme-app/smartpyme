export interface AlertasResumenBanner {
  alertasActivas: number;
  alertasTotal: number;
  enviarianHoy: number;
  canalWhatsapp: number;
  canalCorreo: number;
  resumenesActivos: number;
  resumenesTotal: number;
}

export interface AlertasResumenesMock {
  banner: AlertasResumenBanner;
  avisos: {
    whatsapp: string;
    correo: string;
    horarioOpciones: { id: string; label: string }[];
    horarioId: string;
  };
  resumenesPeriodicos: AlertasResumenPeriodico[];
  vistaPrevia: {
    remitente: string;
    remitenteVerificado: string;
    semanal: AlertasVistaPreviaMensaje;
    mensual: AlertasVistaPreviaMensaje;
  };
}

export interface AlertasResumenPeriodico {
  id: 'semana' | 'mes';
  titulo: string;
  subtitulo: string;
  activo: boolean;
  whatsapp: boolean;
  correo: boolean;
}

export interface AlertasVistaPreviaMensaje {
  titulo: string;
  secciones: AlertasVistaPreviaSeccion[];
  cierre?: string;
  marcaTiempo: string;
}

export interface AlertasVistaPreviaSeccion {
  titulo: string;
  lineas: string[];
  numerada?: boolean;
}

export type AlertasCatalogoEstado = 'sin_novedad' | 'enviaria_hoy' | 'apagada';

export type AlertasCatalogoConfigTipo = 'sin_ajustes' | 'variacion_pct' | 'select';

export interface AlertasCatalogoConfig {
  tipo: AlertasCatalogoConfigTipo;
  valor?: number;
  prefijo?: string;
  opciones?: { id: string; label: string }[];
  valorSelect?: string;
}

export interface AlertasCatalogoItem {
  id: string;
  titulo: string;
  descripcion: string;
  urgente?: boolean;
  activo: boolean;
  whatsapp: boolean;
  correo: boolean;
  estado: AlertasCatalogoEstado;
  config: AlertasCatalogoConfig;
  vistaPreviaWhatsapp?: string;
  expandidoInicial?: boolean;
}

export interface AlertasCatalogoCategoria {
  id: string;
  titulo: string;
  alertas: AlertasCatalogoItem[];
}
