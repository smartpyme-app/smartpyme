import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { ClienteAnticiposComponent } from './cliente-anticipos.component';

describe('ClienteAnticiposComponent', () => {
  let component: ClienteAnticiposComponent;
  let fixture: ComponentFixture<ClienteAnticiposComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ ClienteAnticiposComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(ClienteAnticiposComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
