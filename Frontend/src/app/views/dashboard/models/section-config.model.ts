/**
 * Interfaz para la configuración de secciones del dashboard
 * Permite definir nuevas secciones de forma escalable
 */
export interface SectionConfig {
  nombre: string;
  activo: boolean;
  componente: string;
  requierePresupuesto?: boolean;
  filtrosAdicionales?: string[];
}

/**
 * Interfaz base para componentes de sección
 * Todos los componentes de sección deben recibir datos como input
 */
export interface DashboardSectionComponent {
  datos: any;
}
