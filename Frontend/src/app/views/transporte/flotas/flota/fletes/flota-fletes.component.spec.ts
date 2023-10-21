import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { FlotaFletesComponent } from './flota-fletes.component';

describe('FlotaFletesComponent', () => {
  let component: FlotaFletesComponent;
  let fixture: ComponentFixture<FlotaFletesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ FlotaFletesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(FlotaFletesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
