import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { LibroIvaPaisService } from '@views/contabilidad/libro-iva-shared/libro-iva-pais.service';

/** Redirige /libro-iva/general al libro IVA del país correspondiente. */
@Component({
  selector: 'app-libro-iva-redirect',
  standalone: true,
  template: '',
})
export class LibroIvaRedirectComponent implements OnInit {
  constructor(
    private router: Router,
    private libroIvaPais: LibroIvaPaisService
  ) {}

  ngOnInit(): void {
    void this.router.navigate(this.libroIvaPais.rutaInicioLibroIva(), { replaceUrl: true });
  }
}
