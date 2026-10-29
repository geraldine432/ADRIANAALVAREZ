import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';

interface Product {
  id: number;
  name: string;
  price: number;
  stock: number;
  image?: string | null;
}

interface SaleDetail {
  product: string;
  quantity: number;
  subtotal: number;
  discount_percent: number;
  discount_amount: number;
  subtotal_with_discount: number;
}

interface Sale {
  id: number;
  total: number;
  total_discount: number;
  created_at: string;
  details: SaleDetail[];
}

interface CartItem {
  product: Product;
  quantity: number;
  discountPercent: number;
}

@Component({
  selector: 'app-home',
  templateUrl: './home.component.html',
  styleUrls: []
})
export class HomeComponent implements OnInit {
  private readonly apiUrl = '/api';

  products: Product[] = [];
  sales: Sale[] = [];
  cart: CartItem[] = [];
  loadingProducts = true;
  loadingSales = true;
  submitting = false;
  errorMessage = '';
  successMessage = '';
  apiOnline = false;

  constructor(private readonly http: HttpClient) {}

  ngOnInit(): void {
    this.checkApi();
    this.loadProducts();
    this.loadSales();
  }

  get cartDiscount(): number {
    return this.cart.reduce((total, item) => total + Math.round(item.product.price * item.quantity * item.discountPercent) / 100, 0);
  }

  get orderDiscountApplied(): boolean {
    return this.cart.length > 0 && this.cart.every(item => item.discountPercent === 10);
  }
  get cartTotal(): number {
    return this.cart.reduce((total, item) => total + item.product.price * item.quantity - Math.round(item.product.price * item.quantity * item.discountPercent) / 100, 0);
  }

  get totalUnits(): number {
    return this.products.reduce((total, product) => total + product.stock, 0);
  }

  get totalRevenue(): number {
    return this.sales.reduce((total, sale) => total + Number(sale.total), 0);
  }

  get cartCount(): number {
    return this.cart.reduce((total, item) => total + item.quantity, 0);
  }

  loadProducts(): void {
    this.loadingProducts = true;
    this.http.get<Product[]>(`${this.apiUrl}/products`).subscribe({
      next: products => { this.products = products; this.loadingProducts = false; this.apiOnline = true; this.errorMessage = ''; },
      error: () => { this.loadingProducts = false; this.errorMessage = 'No se pudo conectar con la API. Comprueba que el servicio esté iniciado y vuelve a cargar.'; }
    });
  }

  checkApi(): void {
    this.http.get<{ status: string }>(`${this.apiUrl}/test`).subscribe({
      next: response => { this.apiOnline = response.status === 'ok'; },
      error: () => { this.apiOnline = false; }
    });
  }

  retryConnection(): void {
    this.clearMessages();
    this.checkApi();
    this.loadProducts();
    this.loadSales();
  }

  loadSales(): void {
    this.loadingSales = true;
    this.http.get<Sale[]>(`${this.apiUrl}/sales`).subscribe({
      next: sales => { this.sales = sales; this.loadingSales = false; },
      error: () => { this.loadingSales = false; }
    });
  }

  addToCart(product: Product): void {
    this.clearMessages();
    const item = this.cart.find(entry => entry.product.id === product.id);
    if (item) {
      if (item.quantity >= product.stock) { this.errorMessage = 'No hay más unidades disponibles para agregar.'; return; }
      item.quantity++;
    } else {
      this.cart.push({ product, quantity: 1, discountPercent: 0 });
    }
  }

  setDiscount(item: CartItem, event: Event): void {
    const input = event.target as HTMLInputElement;
    item.discountPercent = Math.min(10, Math.max(0, Number(input.value) || 0));
    input.value = String(item.discountPercent);
  }

  toggleOrderDiscount(): void {
    const removeDiscount = this.cart.length > 0 && this.cart.every(item => item.discountPercent === 10);
    this.cart.forEach(item => item.discountPercent = removeDiscount ? 0 : 10);
  }
  changeQuantity(item: CartItem, amount: number): void {
    const next = item.quantity + amount;
    if (next < 1) { this.removeFromCart(item); return; }
    if (next > item.product.stock) { this.errorMessage = 'La cantidad supera el stock disponible.'; return; }
    item.quantity = next;
  }

  removeFromCart(item: CartItem): void {
    this.cart = this.cart.filter(entry => entry.product.id !== item.product.id);
  }

  createSale(): void {
    if (!this.cart.length || this.submitting) return;
    this.clearMessages();
    this.submitting = true;
    this.http.post<{ message: string; sale_id: number; total: number }>(`${this.apiUrl}/sales`, {
      user_id: 1,
      items: this.cart.map(item => ({ product_id: item.product.id, quantity: item.quantity, discount_percent: item.discountPercent }))
    }).subscribe({
      next: response => {
        this.successMessage = `${response.message}. Venta #${response.sale_id} · ${this.formatCurrency(response.total)}`;
        this.cart = [];
        this.submitting = false;
        this.loadProducts();
        this.loadSales();
      },
      error: error => {
        this.errorMessage = error?.error?.message || 'No se pudo registrar la venta. Revisa el stock e inténtalo de nuevo.';
        this.submitting = false;
        this.loadProducts();
      }
    });
  }

  deleteSale(sale: Sale): void {
    if (!window.confirm(`¿Eliminar la venta #${sale.id}? El stock vendido se devolverá al inventario.`)) return;
    this.clearMessages();
    this.http.delete<{ message: string }>(`${this.apiUrl}/sales/${sale.id}`).subscribe({
      next: response => {
        this.successMessage = response.message;
        this.loadProducts();
        this.loadSales();
      },
      error: error => { this.errorMessage = error?.error?.message || 'No se pudo eliminar la venta.'; }
    });
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB', maximumFractionDigits: 2 }).format(Number(value));
  }

  private clearMessages(): void {
    this.errorMessage = '';
    this.successMessage = '';
  }
}
