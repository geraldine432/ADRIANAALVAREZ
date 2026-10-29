# Mercatech: ventas e inventario

Aplicación web para consultar productos, registrar ventas con descuentos y administrar el inventario. La interfaz está hecha con Angular 16, la API con Laravel 11 y la base de datos con MySQL 8.

## Requisitos

La forma más sencilla de ejecutar el proyecto es con Docker. Instala:

- Docker Desktop (incluye Docker Compose).
- Git, para clonar el repositorio.

Para trabajar sin Docker también necesitarás PHP 8.2 o superior con las extensiones de Laravel y `pdo_mysql`, Composer, Node.js 20 con npm y MySQL 8.

## Instalación y ejecución con Docker

Desde la carpeta raíz del proyecto, donde está `docker-compose.yml`:

```powershell
git clone https://github.com/geraldine432/ADRIANAALVAREZ.git
cd ADRIANAALVAREZ
docker compose up --build -d
```

Espera a que los servicios estén listos y abre [http://localhost:8080](http://localhost:8080). Docker Compose construye Angular y Laravel, inicia MySQL, aplica las migraciones y carga el usuario demo y los productos de ejemplo. La API queda disponible bajo `http://localhost:8080/api`.

Comandos útiles:

```powershell
docker compose ps                  # estado de los contenedores
docker compose logs -f             # registros de todos los servicios
docker compose logs -f api         # registros de Laravel
docker compose down                # detener y conservar los datos
docker compose down -v             # detener y borrar también la base de datos
```

`docker compose down -v` elimina el volumen de MySQL y, con él, las ventas y cambios de inventario guardados.

## Ejecución para desarrollo local (sin Docker)

1. Crea una base de datos MySQL llamada `mercatech_db` y configura el usuario y contraseña en `api-sales/.env` (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`). Puedes partir de `api-sales/.env.example`.
2. En una terminal, instala y prepara Laravel:

   ```powershell
   cd api-sales
   Copy-Item .env.example .env
   composer install
   php artisan key:generate
   php artisan migrate --seed
   php artisan serve
   ```

   La API se ejecutará en `http://127.0.0.1:8000`. Si `Copy-Item` indica que `.env` ya existe, conserva el archivo y edítalo para ajustar la conexión a MySQL.
3. En otra terminal, instala e inicia Angular:

   ```powershell
   cd app-sales
   npm ci
   npm start
   ```

   Abre la dirección que Angular muestre en la terminal (normalmente `http://localhost:4200`). El proxy de desarrollo reenvía `/api` a Laravel en el puerto 8000.

Para compilar Angular para producción: `cd app-sales; npm run build`. Para ejecutar las pruebas configuradas de Angular: `cd app-sales; npm test`. Las pruebas de Laravel se ejecutan con `cd api-sales; php artisan test`.

## Cómo funciona

- Angular consulta productos y ventas a la API y presenta el catálogo, el carrito y el historial.
- Al confirmar la compra, la interfaz envía `user_id` e ítems (producto, cantidad y descuento) a Laravel.
- Laravel valida los datos y el descuento (0–10%), verifica y bloquea el inventario dentro de una transacción, calcula los importes y registra la venta y sus detalles. Si falta stock, la operación completa se rechaza sin crear una venta parcial.
- Al eliminar una venta, la API elimina sus detalles y devuelve las unidades al inventario.
- MySQL conserva productos, ventas y detalles. La moneda presentada por la interfaz es BOB (bolivianos).

El seeder crea dos usuarios de demostración (`admin@test.com` y `master@test.com`, ambos con contraseña `password`) y productos de muestra. La API actual no implementa autenticación y la creación de ventas está limitada al usuario con ID 1; estas credenciales son únicamente para desarrollo local.

## API

Todas las rutas aceptan/responden JSON y usan el prefijo `/api`.

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/api/test` | Estado y versión de la API |
| `GET` | `/api/products` | Lista de productos, precios y existencias |
| `GET` | `/api/sales` | Historial con totales y detalles |
| `POST` | `/api/sales` | Registra una venta y descuenta existencias |
| `DELETE` | `/api/sales/{id}` | Elimina una venta y restituye existencias |

Ejemplo de cuerpo para crear una venta:

```json
{
  "user_id": 1,
  "items": [
    { "product_id": 1, "quantity": 2, "discount_percent": 10 }
  ]
}
```

La cantidad debe ser un entero positivo, no se puede repetir un producto en los ítems y el descuento admite hasta dos decimales entre 0 y 10. Los datos inválidos o el stock insuficiente devuelven `422`; una creación correcta devuelve `201`. Se incluye una colección de Postman en `postman/Mercatech API.postman_collection.json`; importa el archivo y usa `http://localhost:8080` como `baseUrl` con Docker o `http://127.0.0.1:8000` en desarrollo local.

## Estructura

- `app-sales/`: aplicación Angular, estilos, proxy y configuración de Nginx.
- `api-sales/`: API Laravel, modelos, controladores, migraciones y seeders.
- `postman/`: colección para probar los endpoints.
- `docker-compose.yml`: servicios de MySQL, API y web.

Para un despliegue real, define claves y contraseñas propias mediante variables de entorno y habilita HTTPS. No uses las credenciales locales de demostración en producción.
