import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CrearAjusteComponent } from './crear-ajuste.component';

describe('CrearAjusteComponent', () => {
  let component: CrearAjusteComponent;
  let fixture: ComponentFixture<CrearAjusteComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CrearAjusteComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CrearAjusteComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
