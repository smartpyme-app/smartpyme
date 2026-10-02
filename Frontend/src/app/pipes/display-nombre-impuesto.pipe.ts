import { Pipe, PipeTransform, inject } from '@angular/core';
import { ApiService } from '@services/api.service';
import { displayNombreImpuesto } from '@utils/impuesto-display.util';

@Pipe({
  name: 'displayNombreImpuesto',
  standalone: true,
})
export class DisplayNombreImpuestoPipe implements PipeTransform {
  private readonly api = inject(ApiService);

  transform(
    nombre: string | null | undefined,
    empresa?: { cod_pais?: string | null; pais?: string | null } | null
  ): string {
    const emp = empresa ?? this.api.auth_user()?.empresa ?? null;
    return displayNombreImpuesto(nombre, emp);
  }
}
