import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, of } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { FuncionalidadesService } from '../functionalities.service';

export interface ChatMessage {
  sender: 'user' | 'bot';
  text: string;
  timestamp: Date;
  suggestions?: string[];
}

export interface BedrockRequest {
  prompt: string;
  conversationId?: number | null;
  history?: { role: string; content: string }[];
  maxTokens?: number;
  temperature?: number;
  topP?: number;
}

@Injectable({
  providedIn: 'root',
})
export class ChatService {
  private currentConversationId: number | null = null;

  private readonly SUGERENCIAS: string[] = [
    'Ventas vs gastos del mes',
    'Cuentas por cobrar a la fecha',
    'Cuentas por pagar vencidas',
    'Flujo de efectivo del mes actual',
    'Comparativa de ventas con el mes anterior',
    'Proyección de ingresos para el próximo mes',
    'Estado de resultados mensual',
    'Facturas pendientes por pagar',
    'Resumen de impuestos a pagar',
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
      sender: 'bot',
      text: '<p>¡Hola! Soy Lucas, tu asistente financiero. ¿En qué puedo ayudarte hoy?</p>',
      timestamp: new Date(),
      suggestions: this.getRandomSuggestions(3)
    },
  ]);
  messages$ = this.messagesSubject.asObservable();

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
  }

  verificarAcceso(): void {
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

  closeDrawer() {
    this.drawerOpenSubject.next(false);
  }

  resetChat() {
    this.closeDrawer();
    this.currentConversationId = null;
    this.messagesSubject.next([
      {
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

  // Preparar el historial de conversación en formato para Bedrock
  private prepareConversationHistory(
    messages: ChatMessage[]
  ): { role: string; content: string }[] {
    return messages.map((msg) => ({
      role: msg.sender === 'user' ? 'user' : 'assistant',
      content: msg.text,
    }));
  }

  // Llamada a la API de Bedrock - La configuración está en el backend
  private callBedrockAPI(
    prompt: string,
    history: { role: string; content: string }[]
  ) {
    const request: BedrockRequest = {
      prompt,
      history,
      // La configuración de maxTokens, temperature, etc. se maneja en el backend
    };

    return this.http.post<{ message: string; suggestions?: string[] }>(
      `${environment.API_URL}/api/chat/bedrock`,
      request
    );
  }

  sendMessage(text: string) {
    // Verificar acceso antes de enviar mensaje
    if (!this.tieneAccesoSubject.value) {
      console.warn('La empresa no tiene acceso a la funcionalidad de chat');
      return;
    }

    if (!text.trim()) return;

    const messages = this.messagesSubject.value;
    const userMessage: ChatMessage = {
      sender: 'user',
      text,
      timestamp: new Date(),
    };

    // Añadir mensaje del usuario
    this.messagesSubject.next([...messages, userMessage]);

    // Preparar el historial para enviar a Bedrock
    const conversationHistory = this.prepareConversationHistory(messages);

    // Indicar que estamos cargando
    this.loadingSubject.next(true);

    // Llamada a la API de Bedrock a través de nuestro backend
    this.callBedrockAPI(text, conversationHistory).subscribe({
      next: (response) => {
        const botMessage: ChatMessage = {
          sender: 'bot',
          text: response.message,
          timestamp: new Date(),
          suggestions: response.suggestions || []
        };
        this.messagesSubject.next([...this.messagesSubject.value, botMessage]);
        this.loadingSubject.next(false);
      },
      error: (error) => {
        console.error('Error al procesar la consulta:', error);
        const errorMessage: ChatMessage = {
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

  getConversationHistory(): Observable<any> {
    // Verificar acceso antes de obtener historial
    if (!this.tieneAccesoSubject.value) {
      console.warn('La empresa no tiene acceso a la funcionalidad de chat');
      return of(null); // Devolver Observable vacío
    }

    return this.http.get<any>(`${environment.API_URL}/api/chat/history`);
  }

  /**
   * Obtiene una conversación específica por su ID
   */
  getConversation(id: number): Observable<any> {
    // Verificar acceso antes de obtener conversación
    if (!this.tieneAccesoSubject.value) {
      console.warn('La empresa no tiene acceso a la funcionalidad de chat');
      return of(null); // Devolver Observable vacío
    }

    return this.http.get<any>(
      `${environment.API_URL}/api/chat/conversation/${id}`
    );
  }

  /**
   * Carga una conversación existente en el chat
   */
  loadConversation(id: number) {
    // Verificar acceso antes de cargar conversación
    if (!this.tieneAccesoSubject.value) {
      console.warn('La empresa no tiene acceso a la funcionalidad de chat');
      return;
    }

    this.getConversation(id).subscribe({
      next: (response) => {
        // Convertir los mensajes del formato de la base de datos al formato del chat
        const messages: ChatMessage[] = response.messages.map((msg: any) => ({
          sender: msg.sender as 'user' | 'bot',
          text: msg.content,
          timestamp: new Date(msg.created_at),
          suggestions: msg.metadata?.suggestions || []
        }));

        // Establecer la conversación actual
        this.currentConversationId = id;
        this.messagesSubject.next(messages);
      },
      error: (error) => {
        console.error('Error al cargar la conversación:', error);
        this.messagesSubject.next([
          {
            sender: 'bot',
            text: '<p>No se pudo cargar la conversación. Por favor, intenta de nuevo.</p>',
            timestamp: new Date(),
          },
        ]);
      },
    });
  }

  /**
   * Inicia una nueva conversación
   */
  startNewConversation() {
    // Verificar acceso antes de iniciar nueva conversación
    if (!this.tieneAccesoSubject.value) {
      console.warn('La empresa no tiene acceso a la funcionalidad de chat');
      return;
    }

    this.currentConversationId = null;
    this.messagesSubject.next([
      {
        sender: 'bot',
        text: '<p>¡Hola! Soy Lucas, tu asistente financiero. ¿En qué puedo ayudarte hoy?</p>',
        timestamp: new Date(),
        suggestions: this.getRandomSuggestions(3)
      },
    ]);
  }

  /**
   * Crea una nueva conversación en el servidor
   */
  createNewConversation(title?: string): Observable<any> {
    // Verificar acceso antes de crear nueva conversación
    if (!this.tieneAccesoSubject.value) {
      console.warn('La empresa no tiene acceso a la funcionalidad de chat');
      return of(null); // Devolver Observable vacío
    }

    return this.http.post<{ id: number; title: string; created_at: string }>(
      `${environment.API_URL}/api/chat/new`,
      { title }
    );
  }
}