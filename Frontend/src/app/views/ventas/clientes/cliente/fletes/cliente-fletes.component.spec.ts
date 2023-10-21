import { TestBed, async } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { ClienteFletesComponent } from './cliente-fletes.component';

describe('ClienteFletesComponent', () => {
  beforeEach(async(() => {
    TestBed.configureTestingModule({
      imports: [
        RouterTestingModule
      ],
      declarations: [
        ClienteFletesComponent
      ],
    }).compileComponents();
  }));

  it('should create the app', () => {
    const fixture = TestBed.createComponent(ClienteFletesComponent);
    const app = fixture.debugElement.componentInstance;
    expect(app).toBeTruthy();
  });

  it(`should have as title 'wproject'`, () => {
    const fixture = TestBed.createComponent(ClienteFletesComponent);
    const app = fixture.debugElement.componentInstance;
    expect(app.title).toEqual('wproject');
  });

  it('should render title in a h1 tag', () => {
    const fixture = TestBed.createComponent(ClienteFletesComponent);
    fixture.detectChanges();
    const compiled = fixture.debugElement.nativeElement;
    expect(compiled.querySelector('h1').textContent).toContain('Welcome to wproject!');
  });
});
