import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ConfiguracionClienteComponent } from './configuracion-cliente.component';

describe('ConfiguracionClienteComponent', () => {
  let component: ConfiguracionClienteComponent;
  let fixture: ComponentFixture<ConfiguracionClienteComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ ConfiguracionClienteComponent ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ConfiguracionClienteComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
