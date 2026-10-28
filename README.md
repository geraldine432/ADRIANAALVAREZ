# Sistema ADRIANAALVAREZ — prueba técnica

Aplicación de gestión de ventas compuesta por una API REST en Laravel (`api-sales`) y una interfaz web en Angular (`app-sales`). La API permite consultar el catálogo, registrar ventas con sus productos y cantidades, consultar ventas y eliminarlas. Al registrar una venta valida el stock, calcula los importes con los precios guardados y actualiza el inventario dentro de una transacción. La interfaz Angular presenta la aplicación web.

## Requisitos

- PHP 8.2 o superior y Composer 2.
- MySQL 8 (o una versión compatible con Laravel 11).
- Node.js 18.13 o superior y npm (Angular 16).
- Git.

Verifica las instalaciones con `php -v`, `composer --version`, `mysql --version`, `node -v` y `npm -v`.

## Descargar el proyecto

```bash
git clone https://github.com/geraldine432/ADRIANAALVAREZ.git
cd ADRIANAALVAREZ
```

Los comandos siguientes se ejecutan desde la carpeta raíz del repositorio, salvo que se indique otra carpeta.

## Configurar y ejecutar la API

1. Crea una base de datos vacía en MySQL. Por ejemplo, desde el cliente de MySQL:

   ```sql
   CREATE DATABASE mercatech_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Instala las dependencias PHP y crea la configuración local:

   ```bash
   cd api-sales
   composer install
   cp .env.example .env
   ```

   En Windows PowerShell, usa `Copy-Item .env.example .env` en lugar de `cp`.

3. Edita `api-sales/.env` y configura `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` para tu MySQL local. La configuración de ejemplo usa `mercatech_db`, el usuario `root` y contraseña vacía. No compartas tu archivo `.env`.

4. Genera la clave de Laravel, crea las tablas y carga los productos iniciales:

   ```bash
   php artisan key:generate
   php artisan migrate --seed
   ```

5. Inicia el servidor de desarrollo:

   ```bash
   php artisan serve
   ```

   La API quedará disponible en `http://127.0.0.1:8000/api`. Puedes comprobar que responde en `http://127.0.0.1:8000/api/test`.

## Configurar y ejecutar la interfaz Angular

Abre otra terminal desde la raíz del repositorio:

```bash
cd app-sales
npm ci
npm start
```

Abre la dirección que Angular CLI muestre en la terminal (normalmente `http://localhost:4200`). Para compilar una versión de producción ejecuta `npm run build`; el resultado se genera en `app-sales/dist/`.

## Rutas de la API

Todas las rutas usan el prefijo `/api`:

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/products` | Lista los productos disponibles y su stock. |
| `POST` | `/sales` | Registra una venta con un arreglo `items` de `product_id` y `quantity`. |
| `GET` | `/sales` | Lista las ventas y sus detalles. |
| `DELETE` | `/sales/{id}` | Elimina una venta existente. |

Ejemplo para registrar una venta, enviado como JSON a `http://127.0.0.1:8000/api/sales`:

```json
{
  "user_id": 1,
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 3, "quantity": 1 }
  ]
}
```

El usuario de la prueba estática es `1`. Los identificadores de productos válidos dependen de los datos cargados por los seeders. La API obtiene los precios de la base de datos; no se deben enviar en la solicitud.

## Comandos útiles

- Reiniciar tablas y datos de ejemplo: `cd api-sales && php artisan migrate:fresh --seed` (elimina los datos existentes de esa base de datos).
- Inspeccionar rutas Laravel: `cd api-sales && php artisan route:list --path=api`.
- Limpiar cachés de Laravel: `cd api-sales && php artisan optimize:clear`.
- Compilar Angular: `cd app-sales && npm run build`.

En PowerShell, ejecuta cada comando de la lista por separado; `&&` puede sustituirse por una nueva línea.

## Solución de problemas

- **No conecta a MySQL:** confirma que el servicio está activo y que los valores `DB_*` de `api-sales/.env` coinciden con tu instalación.
- **No encuentra las tablas:** desde `api-sales`, ejecuta `php artisan migrate --seed`.
- **No encuentra Composer o PHP:** instala PHP 8.2+ y Composer 2 y vuelve a abrir la terminal.
- **Falla `npm ci`:** usa Node.js 18.13+ y ejecuta el comando dentro de `app-sales`, donde está `package-lock.json`.
- **El puerto está ocupado:** inicia Laravel con `php artisan serve --port=8001` o Angular con `npm start -- --port 4300`.

## Estructura

```text
api-sales/   API Laravel, migraciones y seeders
app-sales/   Aplicación web Angular
```

Cada aplicación conserva también su propio `README.md` con información del framework.
=======
# ADRIANAALVAREZ
PRUEBA TECNICA
>>>>>>> b5601123179a6e3db3978bdc4a15e86e088c13d8
