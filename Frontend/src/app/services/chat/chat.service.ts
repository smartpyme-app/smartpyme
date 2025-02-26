import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

export interface ChatMessage {
  sender: 'user' | 'bot';
  text: string;
  timestamp?: Date;
}

@Injectable({
  providedIn: 'root'
})
export class ChatService {
  private drawerOpenSubject = new BehaviorSubject<boolean>(false);
  drawerOpen$ = this.drawerOpenSubject.asObservable();
  
  private messagesSubject = new BehaviorSubject<ChatMessage[]>([
    { sender: 'bot', text: '¡Hola! ¿En qué puedo ayudarte hoy?', timestamp: new Date() }
  ]);
  messages$ = this.messagesSubject.asObservable();
  
  constructor() {}
  
  toggleDrawer() {
    this.drawerOpenSubject.next(!this.drawerOpenSubject.value);
  }
  
  sendMessage(text: string) {
    if (!text.trim()) return;
    
    const messages = this.messagesSubject.value;
    const userMessage: ChatMessage = {
      sender: 'user',
      text,
      timestamp: new Date()
    };
    
    // Añadir mensaje del usuario
    this.messagesSubject.next([...messages, userMessage]);
    
    // Aquí harías la llamada a tu API de Bedrock
    // Por ahora simulamos una respuesta
    setTimeout(() => {
      const botMessage: ChatMessage = {
        sender: 'bot',
        text: 'Gracias por tu mensaje. Estoy procesando tu consulta.',
        timestamp: new Date()
      };
      this.messagesSubject.next([...this.messagesSubject.value, botMessage]);
    }, 1000);
  }

  closeDrawer() {
    this.drawerOpenSubject.next(false);
  }
}

