import { Component, OnInit } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { GiftCardLookup, GiftCardsService } from '@services/gift-cards.service';

@Component({
  selector: 'app-consulta-saldo-gift-cards',
  standalone: false,
  templateUrl: './consulta-saldo.component.html',
})
export class ConsultaSaldoComponent implements OnInit {
  codigo = '';
  lookupLoading = false;
  card: GiftCardLookup | null = null;
  lookupError = '';

  listLoading = false;
  cards: GiftCardLookup[] = [];
  meta = { current_page: 1, last_page: 1, per_page: 25, total: 0 };

  constructor(
    private giftCardsService: GiftCardsService,
    private alertService: AlertService,
  ) {}

  ngOnInit(): void {
    this.loadList();
  }

  consultar(): void {
    const codigo = this.codigo.trim();
    if (!codigo) {
      this.alertService.warning('Atención', 'Ingrese un código.');
      return;
    }

    this.lookupLoading = true;
    this.lookupError = '';
    this.card = null;

    this.giftCardsService.getByCodigo(codigo).subscribe({
      next: (response) => {
        this.card = response.data;
        this.lookupLoading = false;
      },
      error: (error) => {
        this.lookupError = typeof error === 'string' ? error : 'Gift card no encontrada';
        this.lookupLoading = false;
      },
    });
  }

  loadList(page = 1): void {
    this.listLoading = true;
    this.giftCardsService.list({ paginate: 25, page }).subscribe({
      next: (response) => {
        this.cards = response.data ?? [];
        this.meta = response.meta ?? this.meta;
        this.listLoading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.listLoading = false;
      },
    });
  }

  changePage(page: number): void {
    if (page >= 1 && page <= this.meta.last_page) {
      this.loadList(page);
    }
  }
}
