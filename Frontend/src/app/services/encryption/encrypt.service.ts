import { Injectable } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class EncryptService {
  private salt = 'smartpyme2025'; // Tu salt secreto

  encrypt(id: number): string {
    const data = `${this.salt}_${id}_${Date.now()}`;
    return btoa(data).replace(/[+/=]/g, m => ({'+':'-','/':'_','=':''}[m] || m));
  }

  decrypt(encrypted: string): number {
    try {
      const restored = encrypted.replace(/[-_]/g, m => ({'-':'+','_':'/'}[m] || m));
      const padded = restored + '='.repeat((4 - restored.length % 4) % 4);
      const decoded = atob(padded);
      const match = decoded.match(new RegExp(`^${this.salt}_(\\d+)_\\d+$`));
      return match ? parseInt(match[1]) : 0;
    } catch {
      return 0;
    }
  }

  /**
   * Genera un código promocional dinámico
   * @param prefix Prefijo opcional para el código (ej: 'SMARTPYME', 'BOXFUL', etc.)
   * @param length Longitud del código aleatorio (por defecto 8)
   * @returns Código promocional generado
   */
  generatePromoCode(prefix: string = '', length: number = 8): string {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let randomPart = '';
    
    // Generar parte aleatoria
    for (let i = 0; i < length; i++) {
      randomPart += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    
    // Si hay prefijo, combinarlo con la parte aleatoria
    if (prefix && prefix.trim() !== '') {
      // Asegurar que el prefijo esté en mayúsculas y sin espacios
      const cleanPrefix = prefix.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
      return `${cleanPrefix}${randomPart}`;
    }
    
    // Si no hay prefijo, retornar solo la parte aleatoria
    return randomPart;
  }

  /**
   * Genera un código promocional único basado en un ID
   * @param id ID base para generar el código
   * @param prefix Prefijo opcional
   * @returns Código promocional único
   */
  generatePromoCodeFromId(id: number, prefix: string = 'PROMO'): string {
    const timestamp = Date.now().toString(36).toUpperCase();
    const idEncoded = id.toString(36).toUpperCase().padStart(4, '0');
    return `${prefix}${idEncoded}${timestamp.slice(-4)}`;
  }
}
