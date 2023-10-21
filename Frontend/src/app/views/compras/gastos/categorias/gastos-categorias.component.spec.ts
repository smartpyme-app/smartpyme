import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { GastosCategoriasComponent } from './gastos-categorias.component';

describe('GastosCategoriasComponent', () => {
  let component: GastosCategoriasComponent;
  let fixture: ComponentFixture<GastosCategoriasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ GastosCategoriasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(GastosCategoriasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
