import { loginHostFromHostname } from './login-host';

describe('loginHostFromHostname', () => {
  it('elige contadores en el host del portal', () => {
    expect(loginHostFromHostname('contadores.smartpyme.test')).toBe('contadores');
  });

  it('mantiene el login de la PYME en localhost', () => {
    expect(loginHostFromHostname('localhost')).toBe('default');
  });
});
