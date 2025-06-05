import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/environments/environment';

@Injectable({
  providedIn: 'root'
})
export class FileService {
  private baseUrl = environment.API_URL;

  constructor(private http: HttpClient) {}

  prepareFormData(orden: any, file: File | null): FormData {
    const formData = new FormData();
    
    // Agregar el archivo si existe
    if (file) {
      formData.append('documento_pdf', file);
    }
    
    // Agregar los datos de la orden
    formData.append('datos_orden', JSON.stringify(orden));
    
    return formData;
  }
}