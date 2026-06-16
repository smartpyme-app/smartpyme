import { Component, EventEmitter, Input, OnInit, Output, OnChanges, SimpleChanges } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { BoxfulApiService, BoxfulState, BoxfulCity } from '@services/boxful/boxful-api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-boxful-shipping-selector',
  templateUrl: './boxful-shipping-selector.component.html',
  styleUrls: ['./boxful-shipping-selector.component.css']
})
export class BoxfulShippingSelectorComponent implements OnInit, OnChanges {
  @Input() clienteId!: number;
  @Input() paqueteData: any; // peso, alto, ancho, largo
  @Output() guiaGenerada = new EventEmitter<any>();
  @Output() cerrar = new EventEmitter<void>();

  public clientData: any = null;

  // Asistente (Wizard)
  step: number = 1;

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['clienteId'] && !changes['clienteId'].firstChange) {
      this.resetWizard();
      if (this.clienteId) {
        this.loadAddresses();
        this.loadClientDetails();
      } else {
        this.mode = 'new';
      }
    }
  }

  // Paso 1
  addressForm!: FormGroup;
  states: BoxfulState[] = [];
  filteredCities: BoxfulCity[] = [];
  addresses: any[] = []; // Direcciones del cliente de la BD local
  mode: 'saved' | 'new' = 'saved';
  selectedAddressId: number | null = null;

  // Datos de Bodega/Origen de la Empresa
  originAddresses: any[] = [];
  selectedOriginAddressId: string | null = null;
  loadingOriginAddresses = false;
  originMode: 'saved' | 'new' = 'saved';
  originAddressForm!: FormGroup;
  savingOriginAddress = false;
  filteredOriginCities: BoxfulCity[] = [];

  // Datos del Destinatario
  clienteNombre: string = '';
  clienteApellido: string = '';
  clienteEmail: string = '';

  // Paso 2
  couriers: any[] = [];
  selectedCourierId: number | null = null;

  // Paso 3
  shipmentResult: any = null;

  // Estados comunes
  loading = false;
  loadingCouriers = false;
  generatingGuide = false;
  errorMessage: string | null = null;

  constructor(
    private fb: FormBuilder,
    private boxfulService: BoxfulApiService,
    private alertService: AlertService
  ) { }

  ngOnInit(): void {
    this.initForm();
    this.loadStates();
    this.loadOriginAddresses();
    if (this.clienteId) {
      this.loadAddresses();
      this.loadClientDetails();
    } else {
      this.mode = 'new';
    }
  }

  loadClientDetails(): void {
    if (!this.clienteId) return;
    this.boxfulService.getClientDetails(this.clienteId).subscribe({
      next: (client: any) => {
        if (client) {
          this.clientData = client;
          this.clienteNombre = client.nombre || '';
          this.clienteApellido = client.apellido || '';
          this.clienteEmail = client.correo || client.email || '';
          
          this.fillAddressFormFromClient();
        }
      },
      error: (err: any) => {
        console.error('Error cargando detalles del cliente:', err);
      }
    });
  }

  private initForm(): void {
    this.addressForm = this.fb.group({
      direccion: ['', [Validators.required, Validators.maxLength(500)]],
      referencia: ['', [Validators.maxLength(500)]],
      telefono: ['', [Validators.required, Validators.pattern(/^[0-9+ ]{8,20}$/)]],
      codigo_area: ['503', [Validators.required, Validators.maxLength(10)]],
      stateId: [null, [Validators.required]],
      cityId: [null, [Validators.required]],
      latitud: [null, [Validators.required, Validators.pattern(/^-?[0-9]+(\.[0-9]+)?$/)]],
      longitud: [null, [Validators.required, Validators.pattern(/^-?[0-9]+(\.[0-9]+)?$/)]],
      guardarDireccion: [true] // Opción para guardar la dirección en el cliente al final
    });

    this.originAddressForm = this.fb.group({
      direccion: ['', [Validators.required, Validators.maxLength(500)]],
      referencia: ['', [Validators.required, Validators.maxLength(500)]], // Requerido por la especificación de origen
      telefono: ['', [Validators.required, Validators.pattern(/^[0-9+ ]{8,20}$/)]],
      codigo_area: ['503', [Validators.required, Validators.maxLength(10)]],
      stateId: [null, [Validators.required]],
      cityId: [null, [Validators.required]],
      latitud: [null, [Validators.required, Validators.pattern(/^-?[0-9]+(\.[0-9]+)?$/)]],
      longitud: [null, [Validators.required, Validators.pattern(/^-?[0-9]+(\.[0-9]+)?$/)]]
    });
  }

  loadStates(): void {
    this.loading = true;
    this.boxfulService.getStates().subscribe({
      next: (res: any) => {
        this.loading = false;
        if (res && Array.isArray(res.states)) {
          this.states = res.states;
          this.errorMessage = null;
        } else if (Array.isArray(res)) {
          this.states = res;
          this.errorMessage = null;
        } else {
          console.error('La respuesta de estados no es un arreglo válido:', res);
          this.states = [];
          this.errorMessage = res?.message || res?.error || 'No se pudieron cargar los estados de Boxful (respuesta inválida).';
        }
        this.tryMatchClientLocation();
      },
      error: (err: any) => {
        console.error('Error cargando estados de Boxful:', err);
        this.errorMessage = 'No se pudieron cargar los estados/departamentos de Boxful.';
        this.loading = false;
      }
    });
  }

  loadOriginAddresses(): void {
    this.loadingOriginAddresses = true;
    this.boxfulService.getAddresses().subscribe({
      next: (res: any) => {
        this.loadingOriginAddresses = false;

        let list: any[] = [];
        if (Array.isArray(res)) {
          list = res;
        } else if (res && Array.isArray(res.addresses)) {
          list = res.addresses;
        } else if (res && Array.isArray(res.data)) {
          list = res.data;
        }

        this.originAddresses = list;

        if (this.originAddresses.length > 0) {
          this.selectedOriginAddressId = this.originAddresses[0].id || this.originAddresses[0].addressId;
          this.originMode = 'saved';
        } else {
          this.selectedOriginAddressId = null;
          this.originMode = 'new';
        }
      },
      error: (err: any) => {
        console.error('Error cargando direcciones de origen de Boxful:', err);
        this.loadingOriginAddresses = false;
      }
    });
  }

  loadAddresses(): void {
    if (!this.clienteId) return;
    this.loading = true;
    this.boxfulService.getClientAddresses(this.clienteId).subscribe({
      next: (res: any) => {
        this.loading = false;

        let addressesList: any[] = [];
        if (Array.isArray(res)) {
          addressesList = res;
        } else if (res && Array.isArray(res.addresses)) {
          addressesList = res.addresses;
        } else if (res && Array.isArray(res.data)) {
          addressesList = res.data;
        } else if (res && (res.message || res.error)) {
          this.errorMessage = res.message || res.error;
          console.error('Respuesta con error al obtener direcciones del cliente:', res);
        }

        this.addresses = addressesList;
        this.errorMessage = this.errorMessage || null;

        if (this.addresses.length > 0) {
          // Seleccionar la predeterminada si existe
          const defaultAddr = this.addresses.find(a => a.es_predeterminada);
          if (defaultAddr) {
            this.selectedAddressId = defaultAddr.boxful_address_id;
          } else {
            this.selectedAddressId = this.addresses[0].boxful_address_id;
          }
        } else {
          this.selectedAddressId = null;
        }
      },
      error: (err: any) => {
        console.error('Error cargando direcciones de Boxful del cliente:', err);
        this.errorMessage = 'No se pudieron cargar las direcciones guardadas del cliente.';
        this.loading = false;
      }
    });
  }

  onStateChange(): void {
    const stateId = this.addressForm.get('stateId')?.value;
    const selectedState = this.states.find(s => String(s.id) === String(stateId));

    if (selectedState && selectedState.Cities) {
      this.filteredCities = selectedState.Cities;
    } else {
      this.filteredCities = [];
    }

    this.addressForm.get('cityId')?.setValue(null);
    this.addressForm.patchValue({
      latitud: null,
      longitud: null
    });
  }

  onCityChange(): void {
    const cityId = this.addressForm.get('cityId')?.value;
    const selectedCity = this.filteredCities.find(c => String(c.id) === String(cityId));

    if (selectedCity && selectedCity.latitude !== undefined && selectedCity.longitude !== undefined) {
      this.addressForm.patchValue({
        latitud: selectedCity.latitude,
        longitud: selectedCity.longitude
      });
    } else {
      this.addressForm.patchValue({
        latitud: null,
        longitud: null
      });
    }
  }

  toggleOriginMode(): void {
    this.originMode = this.originMode === 'saved' ? 'new' : 'saved';
    this.errorMessage = null;
    if (this.originMode === 'new') {
      this.originAddressForm.reset({
        direccion: '',
        referencia: '',
        telefono: '',
        codigo_area: '503',
        stateId: null,
        cityId: null,
        latitud: null,
        longitud: null
      });
      this.filteredOriginCities = [];
    }
  }

  onOriginStateChange(): void {
    const stateId = this.originAddressForm.get('stateId')?.value;
    const selectedState = this.states.find(s => String(s.id) === String(stateId));

    if (selectedState && selectedState.Cities) {
      this.filteredOriginCities = selectedState.Cities;
    } else {
      this.filteredOriginCities = [];
    }

    this.originAddressForm.get('cityId')?.setValue(null);
    this.originAddressForm.patchValue({
      latitud: null,
      longitud: null
    });
  }

  onOriginCityChange(): void {
    const cityId = this.originAddressForm.get('cityId')?.value;
    const selectedCity = this.filteredOriginCities.find(c => String(c.id) === String(cityId));

    if (selectedCity && selectedCity.latitude !== undefined && selectedCity.longitude !== undefined) {
      this.originAddressForm.patchValue({
        latitud: selectedCity.latitude,
        longitud: selectedCity.longitude
      });
    } else {
      this.originAddressForm.patchValue({
        latitud: null,
        longitud: null
      });
    }
  }

  guardarNuevaDireccionOrigen(): void {
    if (this.originAddressForm.invalid) {
      this.originAddressForm.markAllAsTouched();
      this.alertService.error('Por favor complete todos los campos de la dirección de origen correctamente.');
      return;
    }

    this.savingOriginAddress = true;
    this.errorMessage = null;

    const formVal = this.originAddressForm.value;
    const payload = {
      address: formVal.direccion,
      referencePoint: formVal.referencia,
      latitude: parseFloat(formVal.latitud),
      longitude: parseFloat(formVal.longitud),
      stateId: formVal.stateId,
      cityId: formVal.cityId,
      addressPhone: formVal.telefono,
      addressAreaCode: formVal.codigo_area
    };

    this.boxfulService.createAddress(payload).subscribe({
      next: (res: any) => {
        this.savingOriginAddress = false;
        this.alertService.success("success", 'Dirección de origen guardada exitosamente.');

        // La API devuelve la dirección creada en res.address o directamente en res
        const createdAddr = res.address || res;
        if (createdAddr) {
          this.originAddresses.push(createdAddr);
          this.selectedOriginAddressId = createdAddr.id || createdAddr.addressId;
          this.originMode = 'saved';
          this.originAddressForm.reset({
            direccion: '',
            referencia: '',
            telefono: '',
            codigo_area: '503',
            stateId: null,
            cityId: null,
            latitud: null,
            longitud: null
          });
          this.filteredOriginCities = [];
        } else {
          this.loadOriginAddresses();
        }
      },
      error: (err: any) => {
        console.error('Error al guardar dirección de origen:', err);
        this.errorMessage = err.error?.message || 'Ocurrió un error al guardar la dirección de origen en Boxful.';
        this.alertService.error(this.errorMessage);
        this.savingOriginAddress = false;
      }
    });
  }

  onModeChange(newMode: 'saved' | 'new'): void {
    this.mode = newMode;
    this.errorMessage = null;
    if (newMode === 'saved') {
      if (this.addresses.length === 0) {
        this.loadAddresses();
      }
    }
  }

  private getDireccionOrigen(): any {
    if (this.selectedOriginAddressId) {
      const selected = this.originAddresses.find(a => String(a.id || a.addressId) === String(this.selectedOriginAddressId));
      if (selected) {
        return {
          id: selected.id || selected.addressId,
          direccion: selected.address,
          referencia: selected.referencePoint || '',
          latitud: selected.latitude,
          longitud: selected.longitude,
          stateId: selected.stateId,
          cityId: selected.cityId,
          telefono: selected.addressPhone,
          codigo_area: selected.addressAreaCode
        };
      }
    }
    return null;
  }

  private getDireccionDestino(): any {
    const destino: any = {};
    if (this.mode === 'saved') {
      const selected = this.addresses.find(a => String(a.boxful_address_id) === String(this.selectedAddressId));
      if (selected) {
        destino.direccion = selected.direccion;
        destino.referencia = selected.referencia || '';
        destino.latitud = parseFloat(selected.latitud);
        destino.longitud = parseFloat(selected.longitud);
        destino.stateId = selected.boxful_state_id;
        destino.cityId = selected.boxful_city_id;
        destino.id = selected.boxful_address_id;
        destino.telefono = selected.telefono;
        destino.codigo_area = selected.codigo_area || '503';
      }
    } else {
      const formVal = this.addressForm.value;
      destino.direccion = formVal.direccion;
      destino.referencia = formVal.referencia || '';
      destino.latitud = parseFloat(formVal.latitud);
      destino.longitud = parseFloat(formVal.longitud);
      destino.stateId = formVal.stateId;
      destino.cityId = formVal.cityId;
      destino.telefono = formVal.telefono;
      destino.codigo_area = formVal.codigo_area || '503';
    }
    return destino;
  }

  // Paso 1: Cotizar envío
  cotizarEnvio(): void {
    this.errorMessage = null;

    // Validar dirección de origen
    if (!this.selectedOriginAddressId) {
      this.errorMessage = 'Debe seleccionar una dirección de origen.';
      this.alertService.error(this.errorMessage);
      return;
    }

    // Validar dirección
    if (this.mode === 'saved') {
      if (!this.selectedAddressId) {
        this.errorMessage = 'Debe seleccionar una dirección guardada.';
        this.alertService.error(this.errorMessage);
        return;
      }
    } else {
      if (this.addressForm.invalid) {
        this.addressForm.markAllAsTouched();
        this.errorMessage = 'Por favor complete todos los campos de la dirección correctamente.';
        this.alertService.error(this.errorMessage);
        return;
      }
    }

    // Validar paqueteData
    if (!this.paqueteData || !this.paqueteData.peso) {
      this.errorMessage = 'Los datos del paquete (peso) son obligatorios para cotizar.';
      this.alertService.error(this.errorMessage);
      return;
    }

    this.loadingCouriers = true;

    // Construir payload según la nueva arquitectura normalizada
    const payload = {
      paquete: {
        peso: parseFloat(this.paqueteData.peso || 0),
        alto: parseFloat(this.paqueteData.alto || 0),
        ancho: parseFloat(this.paqueteData.ancho || 0),
        largo: parseFloat(this.paqueteData.largo || 0),
        valor: parseFloat(this.paqueteData.valor || 50),
        es_fragil: !!this.paqueteData.es_fragil,
        isFragile: !!this.paqueteData.es_fragil
      },
      destino: this.getDireccionDestino(),
      origen: this.getDireccionOrigen(),
      paqueteId: this.paqueteData.id || null
    };

    this.boxfulService.getCouriersAvailable(payload).subscribe({
      next: (res: any) => {
        this.loadingCouriers = false;

        let couriersList: any[] = [];
        if (Array.isArray(res)) {
          couriersList = res;
        } else if (res && Array.isArray(res.data)) {
          couriersList = res.data;
        } else if (res && Array.isArray(res.couriers)) {
          couriersList = res.couriers;
        } else if (res && (res.message || res.error)) {
          this.errorMessage = res.message || res.error;
          this.alertService.error(this.errorMessage);
          return;
        }

        this.couriers = couriersList;

        if (this.couriers.length > 0) {
          this.selectedCourierId = this.couriers[0].id || this.couriers[0].courierId;
          this.step = 2;
        } else {
          this.errorMessage = this.errorMessage || 'No se encontraron paqueterías disponibles para esta dirección y dimensiones.';
          this.alertService.warning('Atención', this.errorMessage);
        }
      },
      error: (err: any) => {
        console.error('Error al cotizar mensajería:', err);
        this.errorMessage = err.error?.message || 'Ocurrió un error al obtener las paqueterías disponibles de Boxful.';
        this.alertService.error(this.errorMessage);
        this.loadingCouriers = false;
      }
    });
  }

  // Paso 2: Generar Guía
  generarGuia(): void {
    if (!this.selectedCourierId) {
      this.errorMessage = 'Debe seleccionar una paquetería.';
      this.alertService.error(this.errorMessage);
      return;
    }

    this.generatingGuide = true;
    this.errorMessage = null;

    const destino = this.getDireccionDestino();

    // Construir payload final para shipment
    const payload = {
      courierId: this.selectedCourierId,
      paquete: {
        peso: parseFloat(this.paqueteData.peso || 0),
        alto: parseFloat(this.paqueteData.alto || 0),
        ancho: parseFloat(this.paqueteData.ancho || 0),
        largo: parseFloat(this.paqueteData.largo || 0),
        valor: parseFloat(this.paqueteData.valor || 50),
        es_fragil: !!this.paqueteData.es_fragil,
        isFragile: !!this.paqueteData.es_fragil
      },
      destino: destino,
      origen: this.getDireccionOrigen(),
      cliente: {
        nombre: this.clienteNombre || 'Cliente',
        apellido: this.clienteApellido || 'Final',
        email: this.clienteEmail || 'cliente@email.com',
        telefono: destino.telefono || '',
        codigo_area: destino.codigo_area || '503'
      },
      clienteId: this.clienteId,
      paqueteId: this.paqueteData.id || null
    };

    this.boxfulService.createShipment(payload).subscribe({
      next: (res: any) => {
        this.shipmentResult = res;
        this.generatingGuide = false;
        this.step = 3;
        this.guiaGenerada.emit(res);

        // Si es una dirección nueva y se marcó para guardar
        if (this.mode === 'new' && this.addressForm.value.guardarDireccion) {
          this.guardarNuevaDireccionEnCliente();
        }
      },
      error: (err: any) => {
        console.error('Error al generar la guía en Boxful:', err);
        this.errorMessage = err.error?.message || 'Ocurrió un error al generar la guía en Boxful.';
        this.alertService.error(this.errorMessage);
        this.generatingGuide = false;
      }
    });
  }

  // Guardar dirección localmente en cliente y Boxful
  private guardarNuevaDireccionEnCliente(): void {
    const formVal = this.addressForm.value;
    const savePayload = {
      alias: 'Dirección de envío',
      direccion: formVal.direccion,
      referencia: formVal.referencia || '',
      telefono: formVal.telefono,
      codigo_area: formVal.codigo_area,
      latitud: parseFloat(formVal.latitud),
      longitud: parseFloat(formVal.longitud),
      boxful_state_id: formVal.stateId,
      boxful_city_id: formVal.cityId,
      es_predeterminada: false
    };

    this.boxfulService.storeClientAddress(this.clienteId, savePayload).subscribe({
      next: (savedAddr: any) => {
        console.log('Dirección guardada exitosamente en el cliente:', savedAddr);
        // Recargar las direcciones guardadas
        this.loadAddresses();
      },
      error: (err: any) => {
        console.error('Error al guardar dirección en el cliente en segundo plano:', err);
      }
    });
  }

  // Reiniciar Wizard
  resetWizard(): void {
    this.step = 1;
    this.couriers = [];
    this.selectedCourierId = null;
    this.shipmentResult = null;
    this.errorMessage = null;
    this.loadOriginAddresses();
    this.originMode = 'saved';
    this.originAddressForm.reset({
      direccion: '',
      referencia: '',
      telefono: '',
      codigo_area: '503',
      stateId: null,
      cityId: null,
      latitud: null,
      longitud: null
    });
    this.filteredOriginCities = [];
    if (this.mode === 'new') {
      this.addressForm.reset({
        direccion: '',
        referencia: '',
        telefono: '',
        codigo_area: '503',
        stateId: null,
        cityId: null,
        latitud: null,
        longitud: null,
        guardarDireccion: true
      });
      this.filteredCities = [];
    } else {
      this.loadAddresses();
    }
  }

  getRecolectionDateInfo(courier: any): { label: string, time: string } {
    const now = new Date();
    const isEarly = now.getHours() < 11;
    return {
      label: isEarly ? 'Hoy' : 'Mañana',
      time: '08:00 - 18:00'
    };
  }

  getDeliveryDateInfo(courier: any): { dateFormatted: string, relativeLabel: string } {
    const deliveryStr = courier.estimatedDelivery || courier.deliveryTime;
    if (!deliveryStr) {
      return { dateFormatted: 'N/A', relativeLabel: 'N/A' };
    }

    const normalizedStr = deliveryStr.replace(' ', 'T');
    const deliveryDate = new Date(normalizedStr);

    if (isNaN(deliveryDate.getTime())) {
      return { dateFormatted: deliveryStr, relativeLabel: 'Próximamente' };
    }

    const day = String(deliveryDate.getDate()).padStart(2, '0');
    const month = String(deliveryDate.getMonth() + 1).padStart(2, '0');
    const year = deliveryDate.getFullYear();
    const dateFormatted = `${day} / ${month} / ${year}`;

    const now = new Date();
    const d1 = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const d2 = new Date(deliveryDate.getFullYear(), deliveryDate.getMonth(), deliveryDate.getDate());
    const diffTime = d2.getTime() - d1.getTime();
    const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

    let relativeLabel = '';
    if (diffDays <= 0) {
      relativeLabel = 'Hoy mismo';
    } else if (diffDays === 1) {
      relativeLabel = 'En 1 día';
    } else {
      relativeLabel = `En ${diffDays} días`;
    }

    return { dateFormatted, relativeLabel };
  }

  closeSelector(): void {
    this.cerrar.emit();
  }

  private fillAddressFormFromClient(): void {
    if (!this.clientData) return;

    const clientAddress = this.clientData.tipo === 'Empresa' ? this.clientData.empresa_direccion : this.clientData.direccion;
    const clientPhone = this.clientData.tipo === 'Empresa' ? this.clientData.empresa_telefono : this.clientData.telefono;

    if (clientAddress && !this.addressForm.get('direccion')?.value) {
      // ponytail: pre-fill shipping selector address from client profile
      this.addressForm.patchValue({
        direccion: clientAddress
      });
    }

    if (clientPhone && !this.addressForm.get('telefono')?.value) {
      // ponytail: clean phone format and pre-fill phone
      const cleanedPhone = String(clientPhone).replace(/[^0-9+ ]/g, '');
      this.addressForm.patchValue({
        telefono: cleanedPhone
      });
    }

    this.tryMatchClientLocation();
  }

  private tryMatchClientLocation(): void {
    if (!this.clientData || !this.states || this.states.length === 0) return;

    const clientState = this.clientData.departamento;
    const clientCity = this.clientData.municipio;

    if (!clientState) return;

    // ponytail: case-insensitive match for department name to auto-fill select dropdown
    const matchedState = this.states.find(s =>
      s.name.toLowerCase().trim() === clientState.toLowerCase().trim()
    );

    if (matchedState) {
      this.addressForm.patchValue({
        stateId: matchedState.id
      });

      this.onStateChange();

      if (clientCity && this.filteredCities.length > 0) {
        // ponytail: case-insensitive match for municipality name to auto-fill select dropdown
        const matchedCity = this.filteredCities.find(c =>
          c.name.toLowerCase().trim() === clientCity.toLowerCase().trim()
        );
        if (matchedCity) {
          this.addressForm.patchValue({
            cityId: matchedCity.id
          });
          this.onCityChange();
        }
      }
    }
  }
}
