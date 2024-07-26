import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { RetencionesComponent } from './retenciones.component';

describe('RetencionesComponent', () => {
  let component: RetencionesComponent;
  let fixture: ComponentFixture<RetencionesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ RetencionesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(RetencionesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
