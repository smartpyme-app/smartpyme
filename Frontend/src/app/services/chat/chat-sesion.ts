export interface ChatSesionGuardado {
  conversationId: string;
  day: string;
  userId: string;
}

/** Hilo a reabrir. null = otro día u otra sesión: toca un chat nuevo. */
export function chatDeSesion(
  stored: ChatSesionGuardado | null,
  userId: string | null,
  today: string,
): string | null {
  if (!stored?.conversationId || !userId || stored.userId !== userId || stored.day !== today) {
    return null;
  }
  return stored.conversationId;
}
