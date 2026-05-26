import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ConsumidorFinalComponent } from './consumidor-final.component';

describe('ConsumidorFinalComponent', () => {
  let component: ConsumidorFinalComponent;
  let fixture: ComponentFixture<ConsumidorFinalComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ConsumidorFinalComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ConsumidorFinalComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
