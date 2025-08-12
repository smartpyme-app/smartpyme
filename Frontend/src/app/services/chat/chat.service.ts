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
  user_id?: number;
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
      user_id: JSON.parse(localStorage.getItem('SP_auth_user')!).id,
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
        // Procesar el mensaje para gestionar SVGs
        const processedMessage = this.processSVGInMessage(response.message);
        
        const botMessage: ChatMessage = {
          sender: 'bot',
          text: processedMessage,
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

  private processSVGInMessage(message: string): string {
    // Verifica si hay un SVG en el mensaje
    if (message.includes('<svg')) {
      // Extraer el SVG
      const svgMatch = message.match(/<svg[\s\S]*?<\/svg>/);
      
      if (svgMatch) {
        const svg = svgMatch[0];
        
        // Obtener dimensiones originales como referencia
        const widthMatch = svg.match(/width="([^"]*)"/);
        const heightMatch = svg.match(/height="([^"]*)"/);
        
        const width = widthMatch ? parseInt(widthMatch[1], 10) : 400;
        const height = heightMatch ? parseInt(heightMatch[1], 10) : 300;
        
        // Extraer título del SVG si existe
        const titleMatch = svg.match(/<title>(.*?)<\/title>/);
        const svgTitle = titleMatch ? titleMatch[1] : 'Gráfico financiero';
        
        // Crear un ID único para este SVG
        const svgId = 'svg-' + new Date().getTime() + '-' + Math.floor(Math.random() * 1000);
        
        // Modificar el SVG para agregarle un ID
        const svgWithId = svg.replace('<svg', `<svg id="${svgId}"`);
        
        // Buscar texto adicional para incluir en la imagen
        let additionalText = '';
        const paragraphAfterSvg = message.match(/<\/svg>[\s\S]*?<p>([\s\S]*?)<\/p>/);
        if (paragraphAfterSvg) {
          additionalText = paragraphAfterSvg[1].replace(/<[^>]*>/g, '').trim();
        }
        
        // Escapar comillas en los textos para evitar problemas con JavaScript
        const escapedTitle = svgTitle.replace(/'/g, "\\'");
        const escapedText = additionalText.replace(/'/g, "\\'");
        
        // Crear un contenedor con opciones de descarga
        const wrappedSvg = `
          <div class="svg-container" style="--svg-width: ${width}px; --svg-height: ${height}px;">
            ${svgWithId}
            <div class="svg-download-container mt-2 d-flex justify-content-end gap-2">
              <button class="btn btn-sm btn-outline-primary svg-download-btn" 
                      onclick="(function(){
                        // Obtener el SVG
                        const svgEl = document.getElementById('${svgId}');
                        if (!svgEl) return;
                        
                        // Crear un canvas con padding extra
                        const padding = 40; // 20px de padding en cada lado
                        const canvasWidth = Math.max(${width} + (padding * 2), 500); // Mínimo 500px de ancho
                        
                        // Función para dividir texto en múltiples líneas
                        function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
                          if (!text) return 0;
                          
                          const words = text.split(' ');
                          let line = '';
                          let lines = 0;
                          
                          for(let n = 0; n < words.length; n++) {
                            const testLine = line + words[n] + ' ';
                            const metrics = ctx.measureText(testLine);
                            const testWidth = metrics.width;
                            
                            if (testWidth > maxWidth && n > 0) {
                              ctx.fillText(line, x, y + (lines * lineHeight));
                              line = words[n] + ' ';
                              lines++;
                            } else {
                              line = testLine;
                            }
                          }
                          
                          // Dibujar la última línea
                          ctx.fillText(line, x, y + (lines * lineHeight));
                          
                          // Devolver el número total de líneas
                          return lines + 1;
                        }
                        
                        // Configuración inicial del canvas
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        // Establecer un tamaño temporal para medir el texto
                        canvas.width = canvasWidth;
                        canvas.height = 1000;
                        
                        // Configurar fuentes para medir el texto
                        ctx.font = '14px Arial';
                        
                        // Calcular el espacio necesario para el texto adicional
                        let textHeight = 0;
                        if ('${escapedText}') {
                          // Medir cuánto espacio necesitará el texto
                          const maxTextWidth = canvasWidth - (padding * 2);
                          const tempLines = '${escapedText}'.split('\\n');
                          let totalLines = 0;
                          
                          tempLines.forEach(tempLine => {
                            const dummyY = 0;
                            const linesUsed = Math.ceil(ctx.measureText(tempLine).width / maxTextWidth);
                            totalLines += Math.max(1, linesUsed);
                          });
                          
                          textHeight = (totalLines * 20) + 30; // 20px por línea + margen
                        }
                        
                        // Ahora establecer dimensiones finales del canvas
                        const canvasHeight = ${height} + (padding * 2) + textHeight + 40; // +40 para título y margen inferior
                        canvas.height = canvasHeight;
                        
                        // Limpiar el canvas y configurar de nuevo
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        
                        // Crear imagen a partir del SVG
                        const svgData = new XMLSerializer().serializeToString(svgEl);
                        const img = new Image();
                        
                        img.onload = function() {
                          // Fondo blanco
                          ctx.fillStyle = 'white';
                          ctx.fillRect(0, 0, canvas.width, canvas.height);
                          
                          // Dibujar la imagen en el canvas con padding
                          ctx.drawImage(img, padding, padding + 25, ${width}, ${height});
                          
                          // Añadir título en la parte superior
                          ctx.font = 'bold 16px Arial';
                          ctx.fillStyle = '#333';
                          ctx.textAlign = 'center';
                          ctx.fillText('${escapedTitle}', canvasWidth / 2, padding / 2 + 16);
                          
                          // Añadir texto adicional si existe
                          if ('${escapedText}') {
                            ctx.font = '14px Arial';
                            ctx.fillStyle = '#555';
                            ctx.textAlign = 'center';
                            
                            const maxTextWidth = canvasWidth - 80; // 40px de margen a cada lado
                            const textY = ${height} + padding + 45;
                            
                            wrapText(ctx, '${escapedText}', canvasWidth / 2, textY, maxTextWidth, 20);
                          }
                          
                          // Añadir marca de agua pequeña
                          ctx.font = '10px Arial';
                          ctx.fillStyle = '#999';
                          ctx.textAlign = 'right';
                          ctx.fillText('Generado por Lucas - ' + new Date().toLocaleDateString(), canvasWidth - 10, canvasHeight - 10);
                          
                          // Convertir canvas a PNG
                          const pngUrl = canvas.toDataURL('image/png');
                          
                          // Crear enlace de descarga
                          const a = document.createElement('a');
                          a.href = pngUrl;
                          a.download = 'grafico-lucas-' + new Date().getTime() + '.png';
                          a.style.display = 'none';
                          document.body.appendChild(a);
                          a.click();
                          document.body.removeChild(a);
                        };
                        
                        // Usar data URI directamente
                        const dataUri = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgData);
                        img.src = dataUri;
                      })()">
                <i class="fa fa-file-image-o"></i> PNG
              </button>
              <button class="btn btn-sm btn-outline-primary svg-download-btn" 
                      onclick="(function(){
                        const svgEl = document.getElementById('${svgId}');
                        if (!svgEl) return;
                        
                        const svgData = new XMLSerializer().serializeToString(svgEl);
                        
                        // Usar data URI directamente
                        const dataUri = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgData);
                        const a = document.createElement('a');
                        a.href = dataUri;
                        a.download = 'grafico-lucas-' + new Date().getTime() + '.svg';
                        a.style.display = 'none';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                      })()">
                <i class="fa fa-file-code-o"></i> SVG
              </button>
            </div>
          </div>
        `;
        
        // Reemplazar el SVG original con la versión envuelta
        return message.replace(svg, wrappedSvg);
      }
    }
    
    return message;
  }

}