import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { BuscadorClientesComponent } from './buscador-clientes.component';

describe('BuscadorClientesComponent', () => {
  let component: BuscadorClientesComponent;
  let fixture: ComponentFixture<BuscadorClientesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ BuscadorClientesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(BuscadorClientesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
