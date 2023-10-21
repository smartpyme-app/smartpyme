import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CategoriasVentasComponent } from './categorias-ventas.component';

describe('CategoriasVentasComponent', () => {
  let component: CategoriasVentasComponent;
  let fixture: ComponentFixture<CategoriasVentasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CategoriasVentasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CategoriasVentasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
