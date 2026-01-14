import { Component, Input } from '@angular/core';

export interface AccountItem {
  name: string;
  amount: number;
}

@Component({
  selector: 'app-accounts-list',
  templateUrl: './accounts-list.component.html',
  styleUrls: ['./accounts-list.component.css']
})
export class AccountsListComponent {
  @Input() title: string = '';
  @Input() accounts: AccountItem[] = [];
  @Input() type: 'receivable' | 'payable' = 'receivable';

  get sortedAccounts(): AccountItem[] {
    return [...this.accounts].sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));
  }

  get maxAmount(): number {
    if (this.accounts.length === 0) return 1;
    return Math.max(...this.accounts.map(a => Math.abs(a.amount)));
  }

  getBarWidth(amount: number): number {
    if (this.maxAmount === 0) return 0;
    return (Math.abs(amount) / this.maxAmount) * 100;
  }

  formatAmount(amount: number): string {
    return new Intl.NumberFormat('es-GT', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  }
}
