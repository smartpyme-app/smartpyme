import { Injectable } from '@angular/core';
import { ApiService } from '../../../services/api.service';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class TransformacionService {
  constructor(private api: ApiService) {}

  guardar(data: any): Observable<any> {
    return this.api.store('transformacion', data);
  }
}
