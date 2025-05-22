import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { LibroComprasSujetosExcluidosComponent } from './libro-compras-sujetos-excluidos.component';

describe('LibroComprasSujetosExcluidosComponent', () => {
  let component: LibroComprasSujetosExcluidosComponent;
  let fixture: ComponentFixture<LibroComprasSujetosExcluidosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ LibroComprasSujetosExcluidosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(LibroComprasSujetosExcluidosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
