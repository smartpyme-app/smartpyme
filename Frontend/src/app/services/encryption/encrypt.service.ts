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
}
