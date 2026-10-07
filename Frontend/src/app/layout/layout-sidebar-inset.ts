/** Ancho del sidebar para alinear header fijo con la columna de contenido. */
export function syncLayoutSidebarInset(): void {
  if (typeof document === 'undefined') {
    return;
  }
  const mobile = typeof window !== 'undefined' && window.innerWidth <= 768;
  if (mobile) {
    document.documentElement.style.setProperty('--app-sidebar-inset', '0px');
    return;
  }
  let collapsed = false;
  try {
    collapsed = JSON.parse(localStorage.getItem('sidebarCollapsed') ?? 'false');
  } catch {
    collapsed = false;
  }
  document.documentElement.style.setProperty('--app-sidebar-inset', collapsed ? '70px' : '280px');
}
