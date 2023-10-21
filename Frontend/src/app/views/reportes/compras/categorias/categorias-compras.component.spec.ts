import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CategoriasComprasComponent } from './categorias-compras.component';

describe('CategoriasComprasComponent', () => {
  let component: CategoriasComprasComponent;
  let fixture: ComponentFixture<CategoriasComprasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CategoriasComprasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CategoriasComprasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
