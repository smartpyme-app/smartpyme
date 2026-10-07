import { chatDeSesion } from './chat-sesion';

describe('chatDeSesion', () => {
  const guardado = {
    conversationId: 'conv-1',
    day: '2026-10-07',
    userId: '7:3',
  };

  it('reabre el mismo hilo el mismo día y la misma sesión', () => {
    expect(chatDeSesion(guardado, '7:3', '2026-10-07')).toBe('conv-1');
  });

  it('abre un chat nuevo al cambiar el día', () => {
    expect(chatDeSesion(guardado, '7:3', '2026-10-08')).toBeNull();
  });

  it('abre un chat nuevo si la sesión es de otro usuario', () => {
    expect(chatDeSesion(guardado, '8:3', '2026-10-07')).toBeNull();
    expect(chatDeSesion(null, '7:3', '2026-10-07')).toBeNull();
    expect(chatDeSesion({ ...guardado, conversationId: '' }, '7:3', '2026-10-07')).toBeNull();
  });
});
