import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CrearCategoriaGastoComponent } from './crear-categoria-gasto.component';

describe('CrearCategoriaGastoComponent', () => {
  let component: CrearCategoriaGastoComponent;
  let fixture: ComponentFixture<CrearCategoriaGastoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CrearCategoriaGastoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CrearCategoriaGastoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
