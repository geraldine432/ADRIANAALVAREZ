import { Component } from '@angular/core';
import { HttpClient } from '@angular/common/http';

@Component({
  selector: 'app-navbar',
  templateUrl: './navbar.component.html',
  styleUrls: ['./navbar.component.scss']
})
export class NavbarComponent {
  apiOnline = false;

  constructor(private readonly http: HttpClient) {
    this.http.get<{ status: string }>('/api/test').subscribe({
      next: response => { this.apiOnline = response.status === 'ok'; },
      error: () => { this.apiOnline = false; }
    });
  }
}
