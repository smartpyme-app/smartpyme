import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { NotificacionesContainerComponent } from './notificaciones-container.component';

describe('NotificacionesContainerComponent', () => {
  let NotificacionAlertasComponent;
  let fixture: ComponeNotificacionAlertasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ NotificacionesContainerComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(NotificacionesContainerComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
