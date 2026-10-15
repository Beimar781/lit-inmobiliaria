# LIT Inmobiliaria — Sistema de gestión inmobiliaria

Proyecto semestral de **Sistemas de Información 1**. Plataforma web para una inmobiliaria de **Santa Cruz de la Sierra (Bolivia)**.
Tecnologías: **Laravel (PHP) + PostgreSQL + Blade (Bootstrap)**. Desarrollo por ciclos (Proceso Unificado).

**Ciclo I (terminado):** CU1 Iniciar sesión · CU2 Cerrar sesión · CU3 Recuperar contraseña · CU4 Gestionar usuarios · CU5 Registrar propiedad · CU6 Modificar propiedad · CU7 Eliminar propiedad (baja lógica).

El repositorio tiene dos carpetas principales: **`backend/`** (el proyecto Laravel: lógica, base de datos, rutas) y **`frontend/`** (las pantallas: vistas Blade). Ver la sección 6.

---

## Resumen: cómo correr el proyecto

Después de tener instalados **PHP 8.3+, Composer, PostgreSQL (con pgAdmin 4) y Git** (sección 1), son solo estos pasos
(todo se hace en el terminal de VS Code; el detalle está en las secciones 2 y 3):

```
git clone https://github.com/Beimar781/lit-inmobiliaria.git
cd lit-inmobiliaria/backend
composer install
copy .env.example .env
php artisan key:generate
```
Luego crea la base `lit_inmobiliaria` en pgAdmin, escribe **tu contraseña de PostgreSQL** en `backend/.env` (sección 3.3) y ejecuta:
```
php artisan migrate:fresh --seed
php artisan serve
```
Abre http://127.0.0.1:8000. Las siguientes veces solo necesitas `php artisan serve` (dentro de `backend`) con PostgreSQL encendido.
Para comprobar que tu instalación está bien, `php artisan test` debe terminar en `passed` (sección 3.6). Si algo falla, mira la sección 8.

**No hay scripts ni programas extra:** el proyecto se corre solo con estos comandos.

---

## 1. Programas que necesitas (una sola vez)

| Programa | Versión | Para qué sirve | Dónde conseguirlo |
|---|---|---|---|
| **PHP** | **8.3 o superior** (el proyecto usa Laravel 13; lo desarrollamos con 8.3) | Ejecuta Laravel | Sección 1.1 |
| **Composer** | 2.x | Instala las librerías de PHP | Sección 1.2 |
| **PostgreSQL + pgAdmin 4** | 15 o superior | Base de datos y su administrador visual (el instalador de PostgreSQL trae pgAdmin) | https://www.postgresql.org/download/windows/ |
| **Git** | cualquiera | Descargar y subir el código | https://git-scm.com |
| **Visual Studio Code** | cualquiera | Editor de código | https://code.visualstudio.com |

Al instalar PostgreSQL anota la **contraseña** que eliges para el usuario `postgres` y deja el puerto **5432**. Cada integrante usa su propia contraseña; no hace falta que sea la misma.

### 1.1. PHP

**Primero comprueba si ya lo tienes.** Abre un terminal (PowerShell o el de VS Code) y ejecuta:
```
php -v
php -m | findstr /i "pgsql sqlite gd"
```
- Si `php -v` muestra **8.3 o superior** y el segundo comando muestra estas **cinco** líneas, **ya está listo**: pasa a la sección 1.2.
  ```
  gd
  pdo_pgsql
  pdo_sqlite
  pgsql
  sqlite3
  ```
  (el orden puede variar; lo importante es que estén las cinco: `pgsql` es para la base de datos, `sqlite` y `gd` para las pruebas automáticas y las fotos).
- Si muestra 8.3+ pero **falta alguna de las cinco**, solo falta activar esa extensión: mira el paso 3 de abajo.
- Si dice que `php` no se reconoce, o es una versión menor a 8.3, instálalo desde cero así:

**Instalar PHP desde cero (Windows):**

1. Descarga el zip de PHP desde https://www.php.net/downloads.php?os=windows : en la página elige *Windows*, *ZIP Downloads* y **versión 8.3** (o la más nueva). Baja la sección **x64 Non Thread Safe** (la recomendada para usar PHP desde el terminal; la *Thread Safe* también funciona) y descarga el **Zip**.
   Si al ejecutar PHP aparece un error de `VCRUNTIME140.dll`, instala el *Visual C++ Redistributable x64* de Microsoft.
2. Crea la carpeta `C:\php` y extrae ahí todo el contenido del zip (debe quedar `C:\php\php.exe`).
3. En `C:\php` copia el archivo `php.ini-development` y pégalo con el nombre **`php.ini`**. Ábrelo con el Bloc de notas o VS Code y quita el `;` del inicio de estas líneas (búscalas con Ctrl + F):
   ```
   extension_dir = "ext"
   extension=curl
   extension=fileinfo
   extension=gd
   extension=mbstring
   extension=openssl
   extension=pdo_pgsql
   extension=pgsql
   extension=pdo_sqlite
   extension=sqlite3
   extension=zip
   ```
   (`extension_dir` aparece como `; extension_dir = "ext"`: quita solo el `;`).
   Busca también estas dos líneas y cámbialas (las fotos de las propiedades pesan más que el límite por defecto de PHP):
   ```
   upload_max_filesize = 8M
   post_max_size = 40M
   ```
   Guarda el archivo.
4. Agrega PHP al PATH. Abre **PowerShell** y pega esto (no necesita administrador):
   ```
   $p = [Environment]::GetEnvironmentVariable("Path","User"); [Environment]::SetEnvironmentVariable("Path", "$p;C:\php", "User")
   ```
5. **Cierra y vuelve a abrir** PowerShell y VS Code, y repite `php -v` y `php -m | findstr /i "pgsql sqlite gd"` (deben salir las cinco líneas de arriba).

**Si al ejecutar PHP aparece "Unable to load dynamic library ..."**: falta la línea `extension_dir = "ext"` sin `;` o el `php.ini` no está en `C:\php`. Revisa el paso 3.

Si ya tenías PHP pero no encuentra el `php.ini`, `php --ini` te muestra cuál usa (línea *Loaded Configuration File*). Si dice `(none)`, todavía no existe: haz el paso 3.
Si tienes **varios PHP** instalados (por ejemplo uno viejo de otro programa), `where.exe php` muestra cuál se usa: el primero de la lista. Debe ser `C:\php\php.exe`; si no, quita del PATH la carpeta del otro PHP.

### 1.2. Composer

Descarga y ejecuta **Composer-Setup.exe** desde https://getcomposer.org/download/ . Cuando te pregunte por PHP, deja que use el que instalaste (lo detecta solo si está en el PATH). Termina con *Next* hasta el final, **cierra y vuelve a abrir** VS Code y comprueba:
```
composer -V
```

## 2. Descargar el proyecto

Elige una carpeta donde guardarás tus proyectos (por ejemplo `C:\proyectos`) y ejecuta en el terminal:

```
cd C:\proyectos
git clone https://github.com/Beimar781/lit-inmobiliaria.git
cd lit-inmobiliaria
code .
```

(`code .` abre la carpeta en VS Code. Si el repositorio es privado, pídele a Beimar que te agregue como colaborador en GitHub.)

## 3. Preparar el proyecto (primera vez)

**3.1. Crear la base de datos vacía en pgAdmin 4**

1. Abre pgAdmin 4 e ingresa la contraseña de tu usuario `postgres`.
2. En el panel izquierdo: **Servers → PostgreSQL → clic derecho en *Databases* → Create → Database…**
3. Nombre: `lit_inmobiliaria` → **Save**.

(Si prefieres el terminal: `psql -U postgres -c "CREATE DATABASE lit_inmobiliaria;"`.)

Los comandos siguientes se ejecutan en el terminal de VS Code, **dentro de la carpeta `backend`**.

**3.2. Instalar las librerías de PHP**
```
cd backend
composer install
```

**3.3. Crear tu archivo `.env` a partir de `.env.example`**

El `.env` guarda la configuración **de tu computadora** (por ejemplo tu contraseña de PostgreSQL). Cada integrante tiene el suyo y **no se sube a GitHub**. El archivo `.env.example` es la plantilla que sí se sube.

1. Cópialo con otro nombre (dentro de `backend`):
   ```
   copy .env.example .env
   ```
2. Genera la clave de la aplicación (llena sola la línea `APP_KEY`):
   ```
   php artisan key:generate
   ```
3. Abre `backend/.env` en VS Code. Las líneas de la base de datos ya vienen listas; **solo cambia `DB_PASSWORD`** por la contraseña que elegiste al instalar PostgreSQL (y `DB_USERNAME` si tu usuario no es `postgres`):
   ```
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=lit_inmobiliaria
   DB_USERNAME=postgres
   DB_PASSWORD=la_contraseña_que_pusiste_al_instalar
   ```
   No cambies nada más. Guarda con **Ctrl + S** (si la pestaña muestra un punto ● está sin guardar).

Si en el futuro se agregan variables nuevas a `.env.example`, cópialas a tu `.env` (el equipo avisará).

**3.4. Crear las tablas y cargar los datos de ejemplo**
```
php artisan migrate:fresh --seed
```
Esto crea las tablas, los 4 usuarios de prueba, las categorías y **8 propiedades de ejemplo con imágenes**. Podrás ver las tablas en pgAdmin: *lit_inmobiliaria → Schemas → public → Tables*.

**3.5. Encender el sistema**
```
php artisan serve
```
Abre en el navegador: **http://127.0.0.1:8000**

Para apagarlo: en el terminal presiona **Ctrl + C**. Para volver a encenderlo: `php artisan serve` (dentro de `backend`).

**3.6. Comprobar que todo quedó bien (opcional, recomendado)**

Con el sistema apagado (o en otro terminal), ejecuta las pruebas automáticas, dentro de `backend`:
```
php artisan test
```
Debe terminar con **`207 passed`** (el número puede crecer cuando se agreguen casos de uso). Es normal que en Windows aparezcan algunos avisos `!` con `rmdir(...)` en las pruebas de CU5 y CU6: son archivos temporales que Windows no deja borrar al instante y no son errores. Si aparecen líneas rojas `FAIL`, mira la sección 8.
Las pruebas usan una base temporal en memoria: **no tocan tus datos de PostgreSQL**.

## 4. Usuarios de prueba

Contraseña de todos: `Lit2026*`

| Rol | Correo | Qué puede hacer |
|---|---|---|
| Administrador | jgarcia@inmobiliaria.com | Todo: usuarios, propiedades, baja de propiedades y **bitácora** |
| Agente Inmobiliario | mtorrez@inmobiliaria.com | Registrar y modificar propiedades |
| Asistente Administrativo | rjustiniano@inmobiliaria.com | Solo inicia sesión (sin módulos en el Ciclo I) |
| Cliente | croca@gmail.com | Solo inicia sesión (sin módulos en el Ciclo I) |

Los correos se guardan y se buscan siempre en **minúsculas**.

**Recuperar contraseña (CU3) en local:** el correo no se envía de verdad. El enlace queda guardado en el archivo
`backend/storage/logs/laravel.log`. Ábrelo en VS Code, presiona **Ctrl + F**, busca `ENLACE DE RECUPERACION` y copia el enlace en el navegador.

## 5. Reglas importantes del sistema

- **Alcance geográfico:** solo se pueden registrar propiedades en **Santa Cruz de la Sierra, dentro del 4.º anillo**. El mapa de "Registrar/Modificar propiedad" muestra el límite; fuera de él el sistema rechaza la propiedad. La configuración está en `backend/config/santacruz.php`.
- **Precios en dólares (USD $).**
- **Baja lógica:** "Eliminar propiedad" no borra el registro, lo pasa a estado *Baja* y guarda el motivo. Una propiedad *Reservada* no puede darse de baja.
- **Imagen de portada:** cada propiedad tiene una imagen principal que se muestra en el listado.
- **Historial de propiedades:** cada registro, cambio y baja de una propiedad queda guardado en la tabla `historial`.
- **Bitácora (Administrador):** registra quién hizo qué, cuándo y desde qué IP (inicios de sesión, intentos fallidos, cambios en usuarios y propiedades). Menú Panel → Bitácora.
- Si el administrador desactiva una cuenta, esa persona pierde el acceso incluso si tenía la sesión abierta.

## 6. Cómo está organizado el código (para la defensa)

El repositorio se divide en **backend** (lógica y datos) y **frontend** (pantallas). Cada **paquete** del documento es una carpeta con el mismo nombre en las dos partes, y cada **caso de uso** es una subcarpeta:

```
lit-inmobiliaria/
├── backend/                         ← proyecto Laravel (lógica, base de datos, rutas)
│   ├── app/Modules/
│   │   ├── Autenticacion/           ← Paquete: Autenticación y Seguridad
│   │   │   ├── routes.php           ← rutas (URLs) del paquete
│   │   │   ├── CU1_IniciarSesion/   ← CU1: controlador + validación
│   │   │   ├── CU2_CerrarSesion/
│   │   │   └── CU3_RecuperarPassword/
│   │   ├── Usuarios/CU4_GestionarUsuarios/
│   │   ├── Propiedades/
│   │   │   ├── CU5_RegistrarPropiedad/
│   │   │   ├── CU6_ModificarPropiedad/
│   │   │   ├── CU7_EliminarPropiedad/
│   │   │   └── Compartido/          ← lo que usan CU5, CU6 y CU7 (formulario, listado, mapa, imágenes)
│   │   └── Reportes/Bitacora/       ← bitácora del administrador
│   ├── app/Models/                  ← una clase por tabla del diagrama de clases
│   ├── app/Services/                ← HistorialService y BitacoraService
│   ├── database/migrations/         ← creación de las tablas en PostgreSQL
│   ├── database/seeders/            ← datos de ejemplo
│   └── tests/                       ← pruebas automáticas
└── frontend/                        ← pantallas (Blade + Bootstrap)
    ├── Autenticacion/ · Usuarios/ · Propiedades/ · Reportes/   ← pantalla de cada CU, misma ruta que en backend
    ├── layouts/                     ← diseño general (menú, estructura de la página)
    ├── components/                  ← campos reutilizables
    └── errors/                      ← pantallas de error (403, 404, 419, 429)
```

Dentro de cada CU del backend hay siempre:

| Archivo | Qué hace |
|---|---|
| `...Controller.php` | La lógica del caso de uso. Sus comentarios del inicio repiten los pasos del flujo principal del documento |
| `...Request.php` | Las validaciones del formulario (campos obligatorios, formatos, reglas) |

Y la pantalla del mismo CU está en `frontend/<Paquete>/<CU>/*.blade.php`.

**Cómo explicar un caso de uso en la defensa** (ejemplo CU5 Registrar propiedad): el usuario abre la URL → la ruta está en `backend/app/Modules/Propiedades/routes.php` → llama a `RegistrarPropiedadController` → que valida con `RegistrarPropiedadRequest` → guarda con los modelos (`Propiedad`, `Ubicacion`, `Imagen`) → deja registro en el historial y la bitácora → muestra la pantalla `frontend/Propiedades/CU5_RegistrarPropiedad/crear.blade.php`.

## 7. Uso diario

Cuando alguien sube cambios y quieres actualizar tu copia (dentro de `backend`):
```
git pull
composer install
php artisan migrate
```
- `php artisan migrate` solo agrega las tablas/columnas nuevas, **no borra datos**.
- Para volver a empezar de cero (borra TODOS los datos y recarga los de ejemplo): `php artisan migrate:fresh --seed`
- `php artisan db:seed --class=PropiedadSeeder` vuelve a cargar las propiedades de ejemplo y **reemplaza las fotos de esas propiedades por las de ejemplo**. Úsalo solo si quieres justamente eso.
- Las imágenes subidas desde el sistema se guardan en `backend/public/uploads` y **no se suben a GitHub**; las de ejemplo están en `backend/database/seeders/imagenes` y sí.

Para subir tus cambios:
```
git status
git add .
git commit -m "Describe lo que hiciste"
git push
```
Nunca subas el archivo `.env` (ya está ignorado por Git).

## 8. Problemas frecuentes

| Mensaje / síntoma | Solución |
|---|---|
| `Failed opening required '.../vendor/autoload.php'` | Falta instalar las librerías: en el terminal, dentro de `backend`, ejecuta `composer install` |
| `could not find driver` | Falta activar una extensión de base de datos de PHP. Si ocurre al correr el sistema, son `pdo_pgsql` y `pgsql`; si ocurre en `php artisan test`, son `pdo_sqlite` y `sqlite3`. Ejecuta `php --ini` para ver cuál es tu `php.ini`, ábrelo, quita el `;` de esas líneas `extension=...`, guarda y abre un terminal nuevo. Comprueba con `php -m \| findstr /i "pgsql sqlite gd"` (deben salir las cinco) |
| `GD extension is not installed` (en `php artisan test`) | Falta `extension=gd` en el `php.ini`. Quita el `;`, guarda, abre un terminal nuevo y comprueba con `php -m \| findstr /i gd` |
| Quité el `;` del `php.ini` pero sigue sin funcionar | Casi siempre es un **segundo PHP** (Laragon, XAMPP, etc.) que va primero en el PATH. Ejecuta `where.exe php` y `php --ini`: el PHP y el `php.ini` que aparecen deben ser los de `C:\php`. Si no, edita ese `php.ini` o quita del PATH la carpeta del otro PHP. Después **cierra y abre** el terminal y VS Code |
| Cambié el `php.ini` y al rato vuelve a estar como antes | Tenías el `php.ini` abierto en VS Code con una versión vieja y la guardó encima. Cierra esa pestaña sin guardar, vuelve a abrir el archivo, repite el cambio y guarda con **Ctrl + S** |
| `Your PHP version ... does not satisfy` / `ext-... is missing` al hacer `composer install` | Tu PHP es menor a 8.3 o falta una extensión (`mbstring`, `openssl`, `fileinfo`, `zip`, `curl`). Revisa la sección 1.1 y `php -v` |
| `cd backend` dice que no existe la carpeta | Tu copia es de la estructura vieja. Dentro de `lit-inmobiliaria` ejecuta `git pull` y revisa con `git branch` que estás en la rama que indique el equipo |
| `VCRUNTIME140.dll` o el PHP se cierra sin mensaje | Instala el *Visual C++ Redistributable x64* de Microsoft y vuelve a abrir el terminal |
| `No application encryption key has been specified` | Falta `php artisan key:generate` (dentro de `backend`, después de crear el `.env`) |
| `could not translate host name` / `SQLSTATE[HY000] [2002]` | `DB_HOST` en tu `.env` debe ser `127.0.0.1` y PostgreSQL debe estar encendido |
| Error de permisos al escribir `storage/logs/laravel.log` o `bootstrap/cache` | Mueve el proyecto a una carpeta simple como `C:\proyectos` (fuera de OneDrive y de *Archivos de programa*) y cierra programas que la tengan abierta (antivirus, OneDrive) |
| `SQLSTATE[08006] ... Connection refused` | PostgreSQL está apagado o el puerto no es 5432. Abre *Servicios* de Windows, busca `postgresql-x64-...` y pulsa **Iniciar** |
| `database "lit_inmobiliaria" does not exist` | Falta el paso 3.1 (crear la base de datos en pgAdmin) |
| `password authentication failed for user "postgres"` | La contraseña de `DB_PASSWORD` en `backend/.env` no es la de tu PostgreSQL |
| Cambié `.env` y no hace efecto | `php artisan config:clear` y reinicia `php artisan serve` |
| Las tablas se crearon en SQLite / no veo mis tablas en pgAdmin | El `.env` no se guardó con `DB_CONNECTION=pgsql`. Guárdalo (Ctrl + S) y repite `php artisan migrate:fresh --seed` |
| `Port 8000 is in use` | Usa otro puerto: `php artisan serve --port=8001` |
| "La página expiró" (419) al subir fotos | Las imágenes son muy pesadas. Máximo 4 MB cada una. Si persiste, sube `post_max_size` y `upload_max_filesize` en tu `php.ini` |
| El mapa se ve gris o sin calles | El diseño funciona sin internet, pero **los mapas necesitan internet** |
| Las propiedades de ejemplo salen sin foto después de `git pull` | `php artisan db:seed --class=PropiedadSeeder` (solo con datos de ejemplo: reemplaza las fotos de esas propiedades) |
| `composer` o `php` "no se reconoce" | Ver sección 1 (instalar PHP/Composer, agregar `C:\php` al PATH y **reabrir** el terminal o VS Code) |

## 9. Trabajo en equipo (cuando se repartan los casos de uso)

Una rama por caso de uso: `git checkout -b cu8-nombre-del-caso`, trabajar, `git push -u origin cu8-nombre-del-caso`
y abrir un *Pull Request* hacia `main` en GitHub. Avisar en el grupo antes de modificar archivos compartidos
(`frontend/layouts/app.blade.php`, `frontend/Autenticacion/CU1_IniciarSesion/panel.blade.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/database/seeders/DatabaseSeeder.php`).
