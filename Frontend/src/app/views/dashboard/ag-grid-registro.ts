import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';

/**
 * Registro de AG Grid. Antes estaba en main.ts, y por eso la librería completa (~1.8 MB sin minificar)
 * se descargaba al abrir la app aunque solo la usa el dashboard. Importar este archivo (por efecto)
 * en cada lugar que use <ag-grid-angular>; registrar dos veces no tiene efecto.
 */
ModuleRegistry.registerModules([AllCommunityModule]);
