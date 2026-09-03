import { Injectable } from '@angular/core';
import { BehaviorSubject, Subject } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { FuncionalidadesService } from '../functionalities.service';
import { processSvgInMessage } from '@utils/svg-message.util';

export interface ChatMessage {
  message_id: string;
  sender: 'user' | 'bot';
  text: string;
  timestamp: Date;
  suggestions?: string[];
}

export interface LucasConversation {
  conversation_id: string;
  title: string;
  status: string;
  created_at: string;
  updated_at: string;
  message_count: number;
}

export interface LucasMessage {
  message_id: string;
  sender: 'user' | 'bot';
  content: string;
  timestamp: string;
  metadata: any;
}

export interface LucasChatResponse {
  message: string;
  suggestions?: string[];
  conversation_id?: string;
  session_id?: string;
}

@Injectable({
  providedIn: 'root',
})
export class ChatService {
  private currentConversationId: string | null = null;
  private localMessageSeq = 0;
  private accesoVerificado = false;

  // Agrupa las peticiones de refresco de la lista de conversaciones para no
  // disparar una petición HTTP por cada mensaje recibido.
  private refreshConversationsSubject = new Subject<void>();
  private refreshConversations$ = this.refreshConversationsSubject.pipe(
    debounceTime(1500)
  );

  private readonly SUGERENCIAS: string[] = [
    'Ventas vs gastos del mes',
    'Cuentas por cobrar a la fecha',
    'Cuentas por pagar vencidas',
    'Flujo de efectivo del mes actual',
    'Comparativa de ventas con el mes anterior',
    'Proyección de ingresos para el próximo mes',
    'Estado de resultados mensual',
    'Rentabilidad del mes actual',
    'Cuentas por cobrar con vencimiento en 30 días',
    'Total de egresos del mes',
    'Ventas comparadas con el presupuesto',
    'Cuentas por pagar próximas a vencer',
    'Flujo de efectivo comparado con mes anterior',
    'Cuentas por pagar vencidas',
  ];

  private drawerOpenSubject = new BehaviorSubject<boolean>(false);
  drawerOpen$ = this.drawerOpenSubject.asObservable();

  private messagesSubject = new BehaviorSubject<ChatMessage[]>([
    {
      message_id: this.newMessageId(),
      sender: 'bot',
      text: '<p>¡Hola! Soy Lucas, tu asistente financiero. ¿En qué puedo ayudarte hoy?</p>',
      timestamp: new Date(),
      suggestions: this.getRandomSuggestions(3)
    },
  ]);
  messages$ = this.messagesSubject.asObservable();

  private conversationsSubject = new BehaviorSubject<LucasConversation[]>([]);
  conversations$ = this.conversationsSubject.asObservable();

  private conversationsLoadingSubject = new BehaviorSubject<boolean>(false);
  conversationsLoading$ = this.conversationsLoadingSubject.asObservable();

  private loadingConversationSubject = new BehaviorSubject<boolean>(false);
  loadingConversation$ = this.loadingConversationSubject.asObservable();

  // Variable para controlar cuando está cargando una respuesta
  private loadingSubject = new BehaviorSubject<boolean>(false);
  loading$ = this.loadingSubject.asObservable();

  // Variable para controlar si la empresa tiene acceso a la funcionalidad de chat
  private tieneAccesoSubject = new BehaviorSubject<boolean>(true); // Por defecto permitimos acceso
  tieneAcceso$ = this.tieneAccesoSubject.asObservable();

  // Slug de la funcionalidad de chat en la base de datos
  private readonly CHAT_FUNCIONALIDAD_SLUG = 'chat-asistente-ia';

  constructor(
    private http: HttpClient,
    private funcionalidadesService: FuncionalidadesService
  ) {
    try {
      const cachedAccess = localStorage.getItem('chat_access');
      if (cachedAccess !== null) {
        this.tieneAccesoSubject.next(cachedAccess === 'true');
      }
    } catch (e) {
      console.warn('Error al leer acceso de chat desde localStorage', e);
    }

    // Refrescar la lista de conversaciones una sola vez tras una ráfaga de
    // mensajes (servicio singleton: no requiere unsubscribe).
    this.refreshConversations$.subscribe(() => this.loadConversations());
  }

  verificarAcceso(): void {
    // Evitar peticiones HTTP duplicadas: este método se invoca desde varios
    // componentes (drawer, speed-dial), pero el servicio es singleton.
    if (this.accesoVerificado) {
      return;
    }
    this.accesoVerificado = true;

    // Nota: Este servicio es singleton (providedIn: 'root'), así que las suscripciones
    // no necesitan unsubscribe porque el servicio vive durante toda la aplicación
    this.funcionalidadesService
      .verificarAcceso(this.CHAT_FUNCIONALIDAD_SLUG)
      .subscribe({
        next: (acceso) => {
          this.tieneAccesoSubject.next(acceso);
          // Guardar en localStorage para futuras cargas
          try {
            localStorage.setItem('chat_access', acceso ? 'true' : 'false');
          } catch (e) {
            console.warn('Error al guardar acceso de chat en localStorage', e);
          }
        },
        error: (error) => {
          console.error('Error al verificar acceso al chat:', error);
          this.tieneAccesoSubject.next(false);
          // Permitir re-verificación en un próximo intento (p. ej. token expirado)
          this.accesoVerificado = false;
        },
      });
  }

  toggleDrawer() {
    // Solo permitir abrir el drawer si tiene acceso
    if (!this.drawerOpenSubject.value && !this.tieneAccesoSubject.value) {
      console.warn('La empresa no tiene acceso a la funcionalidad de chat');
      return;
    }

    this.drawerOpenSubject.next(!this.drawerOpenSubject.value);
  }

  /**
   * Si una petición a Lucas falla por autenticación/autorización (401/403),
   * resetea el estado de verificación para que un próximo intento la repita
   * (cubre la expiración del token de acceso).
   */
  private handleAccessError(error: unknown): void {
    const status = (error as { status?: number })?.status;
    if (status === 401 || status === 403) {
      this.accesoVerificado = false;
      this.tieneAccesoSubject.next(false);
    }
  }

  closeDrawer() {
    this.drawerOpenSubject.next(false);
    // Refrescar el historial al cerrar para reflejar message_count / updated_at
    this.loadConversations();
  }

  resetChat() {
    this.closeDrawer();
    this.currentConversationId = null;
    this.messagesSubject.next([
      {
        message_id: this.newMessageId(),
        sender: 'bot',
        text: '<p>¡Hola! Soy Lucas, tu asistente financiero. ¿Qué te gustaría saber ahora? Te dejo estas recomendaciones:</p>',
        timestamp: new Date(),
        suggestions: this.getRandomSuggestions(3),
      },
    ]);
    this.loadingSubject.next(false);
  }

  handleSuggestionClick(suggestion: string) {
    this.sendMessage(suggestion);
  }

  /**
   * Genera un id único para mensajes locales (no persistidos en Lucas).
   */
  private newMessageId(): string {
    this.localMessageSeq += 1;
    return `local-${Date.now()}-${this.localMessageSeq}`;
  }

  private getRandomSuggestions(count: number): string[] {
    // Hacer una copia del array original para no modificarlo
    const sugerencias = [...this.SUGERENCIAS];
    const resultado: string[] = [];

    // Seleccionar 'count' elementos aleatorios
    for (let i = 0; i < count && sugerencias.length > 0; i++) {
      const indiceAleatorio = Math.floor(Math.random() * sugerencias.length);
      resultado.push(sugerencias[indiceAleatorio]);
      // Eliminar el elemento seleccionado para no repetir
      sugerencias.splice(indiceAleatorio, 1);
    }

    return resultado;
  }

  /**
   * Resuelve la identidad del usuario logueado (user_id + empresa_id) desde
   * localStorage. Lucas agrupa todo el historial por esta identidad.
   */
  private getIdentity(): {
    user_id: number | null;
    empresa_id: number | null;
    user_type: string;
  } {
    try {
      const user = JSON.parse(localStorage.getItem('SP_auth_user') || '{}');
      return {
        user_id: user?.id ?? null,
        empresa_id: user?.id_empresa ?? user?.empresa?.id ?? null,
        user_type: user?.tipo ?? 'Usuario',
      };
    } catch (e) {
      console.warn('Error al leer identidad del usuario', e);
      return { user_id: null, empresa_id: null, user_type: 'Usuario' };
    }
  }

  /**
   * Headers para Lucas. Si se define LUCAS_API_KEY en producción, se envía
   * el header X-API-Key en todas las llamadas.
   */
  private lucasHeaders(): HttpHeaders {
    const apiKey = environment.lucasApiKey;
    if (apiKey) {
      return new HttpHeaders({ 'X-API-Key': apiKey });
    }
    return new HttpHeaders();
  }

  /**
   * Lista las conversaciones del usuario/empresa desde Lucas.
   */
  loadConversations(): void {
    if (!this.tieneAccesoSubject.value) {
      return;
    }

    const { user_id, empresa_id } = this.getIdentity();
    if (user_id == null || empresa_id == null) {
      console.warn('No se pudo resolver user_id/empresa_id para listar conversaciones');
      return;
    }

    this.conversationsLoadingSubject.next(true);

    const params = new HttpParams()
      .set('user_id', String(user_id))
      .set('empresa_id', String(empresa_id))
      .set('limit', '20');

    this.http
      .get<{ conversations: LucasConversation[]; count: number }>(
        `${environment.lucasApiUrl}/conversations`,
        { headers: this.lucasHeaders(), params }
      )
      .subscribe({
        next: (res) => {
          this.conversationsSubject.next(res?.conversations ?? []);
          this.conversationsLoadingSubject.next(false);
        },
        error: (error) => {
          console.error('Error al listar conversaciones:', error);
          this.conversationsSubject.next([]);
          this.conversationsLoadingSubject.next(false);
          this.handleAccessError(error);
        },
      });
  }

  /**
   * Carga los mensajes de una conversación existente.
   */
  openConversation(conversationId: string): void {
    if (!this.tieneAccesoSubject.value) {
      return;
    }

    this.loadingConversationSubject.next(true);

    const params = new HttpParams().set('limit', '50');

    this.http
      .get<{ conversation_id: string; messages: LucasMessage[]; count: number }>(
        `${environment.lucasApiUrl}/conversations/${conversationId}/messages`,
        { headers: this.lucasHeaders(), params }
      )
      .subscribe({
        next: (res) => {
          this.currentConversationId = conversationId;

          const messages = (res?.messages ?? [])
            .map((m) => this.mapLucasMessage(m))
            .sort((a, b) => a.timestamp.getTime() - b.timestamp.getTime());

          this.messagesSubject.next(messages);
          this.loadingConversationSubject.next(false);
        },
        error: (error) => {
          console.error('Error al cargar la conversación:', error);
          this.loadingConversationSubject.next(false);
          this.handleAccessError(error);
          this.messagesSubject.next([
            {
              message_id: this.newMessageId(),
              sender: 'bot',
              text: '<p>No se pudo cargar la conversación. Por favor, intenta de nuevo.</p>',
              timestamp: new Date(),
            },
          ]);
        },
      });
  }

  /**
   * Crea una nueva conversación en Lucas (cierra la activa en el servidor).
   */
  startNewConversation(): void {
    if (!this.tieneAccesoSubject.value) {
      return;
    }

    const { user_id, empresa_id } = this.getIdentity();
    if (user_id == null || empresa_id == null) {
      console.warn('No se pudo resolver user_id/empresa_id para crear conversación');
      return;
    }

    const params = new HttpParams()
      .set('user_id', String(user_id))
      .set('empresa_id', String(empresa_id));

    this.http
      .post<{
        conversation_id: string;
        session_id: string;
        title: string;
        status: string;
        created_at: string;
      }>(`${environment.lucasApiUrl}/conversations/new`, null, {
        headers: this.lucasHeaders(),
        params,
      })
      .subscribe({
        next: (res) => {
          this.currentConversationId = res?.conversation_id ?? null;
          this.messagesSubject.next([
            {
              message_id: this.newMessageId(),
              sender: 'bot',
              text: '<p>¡Hola! Soy Lucas, tu asistente financiero. ¿En qué puedo ayudarte hoy?</p>',
              timestamp: new Date(),
              suggestions: this.getRandomSuggestions(3),
            },
          ]);
          this.loadConversations();
        },
        error: (error) => {
          console.error('Error al crear nueva conversación:', error);
          this.handleAccessError(error);
          this.messagesSubject.next([
            {
              message_id: this.newMessageId(),
              sender: 'bot',
              text: '<p>No se pudo crear una nueva conversación. Por favor, intenta de nuevo.</p>',
              timestamp: new Date(),
            },
          ]);
        },
      });
  }

  private mapLucasMessage(m: LucasMessage): ChatMessage {
    return {
      message_id: m.message_id,
      sender: m.sender === 'user' ? 'user' : 'bot',
      text: processSvgInMessage(m.content),
      timestamp: new Date(m.timestamp),
      suggestions: this.sanitizeSuggestions(m.metadata?.suggestions),
    };
  }

  /**
   * Normaliza las sugerencias provenientes de Lucas/servidor: elimina vacíos,
   * deduplica y trunca textos demasiado largos para que no desborden el chip.
   */
  private sanitizeSuggestions(
    suggestions: unknown
  ): string[] | undefined {
    if (!Array.isArray(suggestions) || suggestions.length === 0) {
      return undefined;
    }

    const MAX_LENGTH = 60;
    const seen = new Set<string>();

    const clean = suggestions
      .map((s) => String(s).trim())
      .filter((s) => s.length > 0)
      .map((s) => (s.length > MAX_LENGTH ? `${s.slice(0, MAX_LENGTH - 1)}…` : s))
      .filter((s) => {
        if (seen.has(s)) {
          return false;
        }
        seen.add(s);
        return true;
      });

    return clean.length > 0 ? clean : undefined;
  }

  /**
   * Envía un mensaje a Lucas vía POST /chat (este endpoint persiste el historial).
   */
  sendMessage(text: string) {
    // Verificar acceso antes de enviar mensaje
    if (!this.tieneAccesoSubject.value) {
      console.warn('La empresa no tiene acceso a la funcionalidad de chat');
      return;
    }

    if (!text.trim()) return;

    const { user_id, empresa_id, user_type } = this.getIdentity();

    const userMessage: ChatMessage = {
      message_id: this.newMessageId(),
      sender: 'user',
      text,
      timestamp: new Date(),
    };

    // Añadir mensaje del usuario
    this.messagesSubject.next([...this.messagesSubject.value, userMessage]);

    // Indicar que estamos cargando
    this.loadingSubject.next(true);

    const payload: any = {
      message: text,
      user_id,
      empresa_id,
      user_type,
      source: 'Web',
    };

    // El contexto de la conversación lo mantiene Lucas (conversation_id)
    if (this.currentConversationId) {
      payload.conversation_id = this.currentConversationId;
    }

    this.http
      .post<LucasChatResponse>(`${environment.lucasApiUrl}/chat`, payload, {
        headers: this.lucasHeaders(),
      })
      .subscribe({
        next: (response) => {
          // Conservar el conversation_id de Lucas para mantener el contexto
          if (response?.conversation_id) {
            this.currentConversationId = response.conversation_id;
          }

          // Procesar el mensaje para gestionar SVGs
          const processedMessage = processSvgInMessage(response.message || '');

          const botMessage: ChatMessage = {
            message_id: this.newMessageId(),
            sender: 'bot',
            text: processedMessage,
            timestamp: new Date(),
            suggestions: this.sanitizeSuggestions(response.suggestions),
          };
          this.messagesSubject.next([...this.messagesSubject.value, botMessage]);
          this.loadingSubject.next(false);

          // Actualizar la lista (message_count / updated_at) de forma diferida
          // para evitar una petición HTTP por cada mensaje.
          this.refreshConversationsSubject.next();
        },
        error: (error) => {
          console.error('Error al procesar la consulta:', error);
          this.handleAccessError(error);
          const errorMessage: ChatMessage = {
            message_id: this.newMessageId(),
            sender: 'bot',
            text: '<p>Lo siento, ha ocurrido un error al procesar tu consulta. Por favor, intenta de nuevo más tarde.</p>',
            timestamp: new Date(),
          };
          this.messagesSubject.next([
            ...this.messagesSubject.value,
            errorMessage,
          ]);
          this.loadingSubject.next(false);
        },
      });
  }

}
