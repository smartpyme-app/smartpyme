import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CrearCategoriaActivoComponent } from './crear-categoria-activo.component';

describe('CrearCategoriaActivoComponent', () => {
  let component: CrearCategoriaActivoComponent;
  let fixture: ComponentFixture<CrearCategoriaActivoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CrearCategoriaActivoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CrearCategoriaActivoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
