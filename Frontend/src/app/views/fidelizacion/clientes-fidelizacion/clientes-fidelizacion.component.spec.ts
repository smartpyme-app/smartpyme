import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ClientesFidelizacionComponent } from './clientes-fidelizacion.component';

describe('ClientesFidelizacionComponent', () => {
  let component: ClientesFidelizacionComponent;
  let fixture: ComponentFixture<ClientesFidelizacionComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ ClientesFidelizacionComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ClientesFidelizacionComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
