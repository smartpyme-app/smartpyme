import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export interface ChatMessage {
  sender: 'user' | 'bot';
  text: string;
  timestamp: Date;
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

  private drawerOpenSubject = new BehaviorSubject<boolean>(false);
  drawerOpen$ = this.drawerOpenSubject.asObservable();

  private messagesSubject = new BehaviorSubject<ChatMessage[]>([
    {
      sender: 'bot',
      text: '¡Hola! ¿En qué puedo ayudarte hoy?',
      timestamp: new Date(),
    },
  ]);
  messages$ = this.messagesSubject.asObservable();

  // Variable para controlar cuando está cargando una respuesta
  private loadingSubject = new BehaviorSubject<boolean>(false);
  loading$ = this.loadingSubject.asObservable();

  constructor(private http: HttpClient) {}

  toggleDrawer() {
    this.drawerOpenSubject.next(!this.drawerOpenSubject.value);
  }

  closeDrawer() {
    this.drawerOpenSubject.next(false);
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

    return this.http.post<{ message: string }>(
      `${environment.API_URL}/api/chat/bedrock`,
      request
    );
  }

  sendMessage(text: string) {
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
        };
        this.messagesSubject.next([...this.messagesSubject.value, botMessage]);
        this.loadingSubject.next(false);
      },
      error: (error) => {
        console.error('Error al procesar la consulta:', error);
        const errorMessage: ChatMessage = {
          sender: 'bot',
          text: 'Lo siento, ha ocurrido un error al procesar tu consulta. Por favor, intenta de nuevo más tarde.',
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

  getConversationHistory() {
    return this.http.get<any>(`${environment.API_URL}/api/chat/history`);
  }

  /**
   * Obtiene una conversación específica por su ID
   */
  getConversation(id: number) {
    return this.http.get<any>(
      `${environment.API_URL}/api/chat/conversation/${id}`
    );
  }

  /**
   * Carga una conversación existente en el chat
   */
  loadConversation(id: number) {
    this.getConversation(id).subscribe({
      next: (response) => {
        // Convertir los mensajes del formato de la base de datos al formato del chat
        const messages: ChatMessage[] = response.messages.map((msg: any) => ({
          sender: msg.sender as 'user' | 'bot',
          text: msg.content,
          timestamp: new Date(msg.created_at),
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
            text: 'No se pudo cargar la conversación. Por favor, intenta de nuevo.',
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
    this.currentConversationId = null;
    this.messagesSubject.next([
      {
        sender: 'bot',
        text: '¡Hola! ¿En qué puedo ayudarte hoy?',
        timestamp: new Date(),
      },
    ]);
  }

  /**
   * Crea una nueva conversación en el servidor
   */
  createNewConversation(title?: string) {
    return this.http.post<{ id: number; title: string; created_at: string }>(
      `${environment.API_URL}/api/chat/new`,
      { title }
    );
  }
}
