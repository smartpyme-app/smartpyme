import { AlertService } from '@services/alert.service';

export function descargarBlob(data: Blob, mime: string, filename: string): void {
  const blob = new Blob([data], { type: mime });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
}

export function manejarErrorDescargaLibroIva(error: unknown, alertService: AlertService): void {
  const err = error as { error?: Blob; status?: number };
  if (err?.error instanceof Blob) {
    err.error.text().then((text: string) => {
      try {
        const errorJson = JSON.parse(text);
        alertService.error({ status: err.status || 409, error: { message: errorJson.message } });
      } catch {
        alertService.error({ status: err.status || 409, error: { message: text } });
      }
    });
  } else {
    alertService.error(error);
  }
}
