import { AlertsHaciendaComponent } from './alerts-hacienda.component';

describe('AlertsHaciendaComponent.mensajeAmigable', () => {
  const c = new AlertsHaciendaComponent();

  it('explica el número de control duplicado', () => {
    expect(c.mensajeAmigable('[identificacion.numeroControl] YA EXISTE UN REGISTRO CON ESE VALOR'))
      .toContain('correlativo');
  });

  it('deja pasar mensajes desconocidos', () => {
    expect(c.mensajeAmigable('No existe llave publica para este nit')).toBeNull();
  });
});
