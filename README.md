# LIT Inmobiliaria — Sistema de gestión inmobiliaria

Proyecto semestral de **Sistemas de Información 1**. Plataforma web para una inmobiliaria de **Santa Cruz de la Sierra (Bolivia)**.
Tecnologías: **Laravel (PHP) + MySQL + Blade (Bootstrap)**. Desarrollo por ciclos (Proceso Unificado).

**Ciclo I (terminado):** CU1 Iniciar sesión · CU2 Cerrar sesión · CU3 Recuperar contraseña · CU4 Gestionar usuarios · CU5 Registrar propiedad · CU6 Modificar propiedad · CU7 Eliminar propiedad (baja lógica).

---

## 1. Programas que necesitas (una sola vez)

| Programa | Para qué sirve | Dónde conseguirlo |
|---|---|---|
| **Laragon (versión Full)** | Trae PHP, MySQL y Composer ya configurados | https://laragon.org |
| **Git** | Descargar y subir el código | https://git-scm.com (Laragon Full ya lo incluye) |
| **Visual Studio Code** | Editor de código | https://code.visualstudio.com |

1. Instala Laragon y ábrelo. Pulsa **Iniciar todo** (se deben ver encendidos *Apache/Nginx* y **MySQL**). MySQL tiene que estar encendido siempre que uses el sistema.
2. Abre VS Code y luego el terminal: menú **Terminal → Nuevo terminal**.
3. Comprueba que todo funciona (cada comando debe mostrar una versión, no un error):
   ```
   php -v
   composer -V
   git --version
   mysql --version
   ```
   - `php -v` debe decir **8.2 o superior**.
   - Si algún comando dice *"no se reconoce"*: en Laragon ve a **Menú → Herramientas → Path → Agregar Laragon al Path**, y **cierra y vuelve a abrir VS Code**.

## 2. Descargar el proyecto

Elige una carpeta donde guardarás tus proyectos (por ejemplo `C:\Proyectos`) y ejecuta en el terminal:

```
cd C:\Proyectos
git clone https://github.com/Beimar781/lit-inmobiliaria.git
cd lit-inmobiliaria
code .
```

(`code .` abre la carpeta en VS Code. Si el repositorio es privado, pídele a Beimar que te agregue como colaborador en GitHub.)

## 3. Preparar el proyecto (primera vez)

Todos los comandos se ejecutan en el terminal de VS Code, **dentro de la carpeta `lit-inmobiliaria`**.

**3.1. Instalar las librerías de PHP**
```
composer install
```

**3.2. Crear tu archivo de configuración `.env`**
```
copy .env.example .env
php artisan key:generate
```

**3.3. Revisar la conexión a la base de datos.** Abre el archivo `.env` y verifica que estas líneas digan exactamente esto
(si no, cámbialas y **guarda el archivo con Ctrl + S**; si la pestaña muestra un punto ● está sin guardar):
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lit_inmobiliaria
DB_USERNAME=root
DB_PASSWORD=
```
Si tu MySQL tiene contraseña para `root`, escríbela en `DB_PASSWORD`.

**3.4. Crear la base de datos vacía** (MySQL debe estar encendido en Laragon)
```
mysql -u root -e "CREATE DATABASE lit_inmobiliaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
Si tu `root` tiene contraseña, agrega `-p` después de `root` y escríbela cuando la pida.
Alternativa sin comandos: en Laragon, clic derecho → **MySQL → Crear base de datos** (nombre: `lit_inmobiliaria`).

**3.5. Crear las tablas y cargar los datos de ejemplo**
```
php artisan migrate:fresh --seed
```
Esto crea las tablas, los 4 usuarios de prueba, las categorías y **8 propiedades de ejemplo con imágenes**.

**3.6. Encender el sistema**
```
php artisan serve
```
Abre en el navegador: **http://127.0.0.1:8000**

Para apagarlo: en el terminal presiona **Ctrl + C**. Para volver a encenderlo: `php artisan serve`.

## 4. Usuarios de prueba

Contraseña de todos: `Lit2026*`

| Rol | Correo | Qué puede hacer |
|---|---|---|
| Administrador | jgarcia@inmobiliaria.com | Todo: usuarios, propiedades, baja de propiedades y **bitácora** |
| Agente Inmobiliario | mtorrez@inmobiliaria.com | Registrar y modificar propiedades |
| Asistente Administrativo | rjustiniano@inmobiliaria.com | Solo inicia sesión (sin módulos en el Ciclo I) |
| Cliente | croca@gmail.com | Solo inicia sesión (sin módulos en el Ciclo I) |

**Recuperar contraseña (CU3) en local:** el correo no se envía de verdad. El enlace queda guardado en el archivo
`storage/logs/laravel.log`. Ábrelo en VS Code, presiona **Ctrl + F**, busca `ENLACE DE RECUPERACION` y copia el enlace en el navegador.

## 5. Reglas importantes del sistema

- **Alcance geográfico:** solo se pueden registrar propiedades en **Santa Cruz de la Sierra, dentro del 4.º anillo**. El mapa de "Registrar/Modificar propiedad" muestra el límite; fuera de él el sistema rechaza la propiedad. La configuración está en `config/santacruz.php`.
- **Precios en dólares (USD $).**
- **Baja lógica:** "Eliminar propiedad" no borra el registro, lo pasa a estado *Baja* y guarda el motivo. Una propiedad *Reservada* no puede darse de baja.
- **Imagen de portada:** cada propiedad tiene una imagen principal que se muestra en el listado.
- **Historial de propiedades:** cada registro, cambio y baja de una propiedad queda guardado en la tabla `historial`.
- **Bitácora (Administrador):** registra quién hizo qué, cuándo y desde qué IP (inicios de sesión, intentos fallidos, cambios en usuarios y propiedades). Menú Panel → Bitácora.
- Si el administrador desactiva una cuenta, esa persona pierde el acceso incluso si tenía la sesión abierta.

## 6. Cómo está organizado el código (para la defensa)

Cada **paquete** del documento es una carpeta en `app/Modules` y cada **caso de uso** es una subcarpeta con todo lo que necesita:

```
app/Modules/
├── Autenticacion/            ← Paquete: Autenticación y Seguridad
│   ├── routes.php            ← rutas (URLs) del paquete
│   ├── CU1_IniciarSesion/    ← CU1: controlador + validación + pantalla
│   ├── CU2_CerrarSesion/
│   └── CU3_RecuperarPassword/
├── Usuarios/CU4_GestionarUsuarios/
├── Propiedades/
│   ├── CU5_RegistrarPropiedad/
│   ├── CU6_ModificarPropiedad/
│   ├── CU7_EliminarPropiedad/
│   └── Compartido/           ← lo que usan CU5, CU6 y CU7 (formulario, listado, mapa, imágenes)
└── Reportes/Bitacora/        ← bitácora del administrador
```

Dentro de cada CU siempre hay lo mismo:

| Archivo | Qué hace |
|---|---|
| `...Controller.php` | La lógica del caso de uso. Sus comentarios del inicio repiten los pasos del flujo principal del documento |
| `...Request.php` | Las validaciones del formulario (campos obligatorios, formatos, reglas) |
| `*.blade.php` | La pantalla que ve el usuario |

Otras carpetas útiles:

| Carpeta | Contenido |
|---|---|
| `app/Models` | Una clase por tabla del diagrama de clases (Usuario, Rol, Propiedad, Imagen...) |
| `database/migrations` | Creación de las tablas (la base de datos) |
| `database/seeders` | Datos de ejemplo (usuarios, categorías, propiedades) |
| `app/Services` | `HistorialService` (historial de propiedades) y `BitacoraService` (bitácora) |
| `resources/views` | Diseño general (`layouts/app.blade.php`) y componentes reutilizables |

**Cómo explicar un caso de uso en la defensa** (ejemplo CU5 Registrar propiedad): el usuario abre la URL → la ruta está en `Propiedades/routes.php` → llama a `RegistrarPropiedadController` → que valida con `RegistrarPropiedadRequest` → guarda con los modelos (`Propiedad`, `Ubicacion`, `Imagen`) → deja registro en el historial y la bitácora → muestra la pantalla `crear.blade.php`.

## 7. Uso diario

Cuando alguien sube cambios y quieres actualizar tu copia:
```
git pull
composer install
php artisan migrate
```
- `php artisan migrate` solo agrega las tablas/columnas nuevas, **no borra datos**.
- Para volver a empezar de cero (borra TODOS los datos y recarga los de ejemplo): `php artisan migrate:fresh --seed`
- Para volver a cargar solo las propiedades de ejemplo y sus fotos: `php artisan db:seed --class=PropiedadSeeder`
- Las imágenes subidas desde el sistema se guardan en `public/uploads` y **no se suben a GitHub**; las de ejemplo están en `database/seeders/imagenes` y sí.

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
| `could not find driver` | Falta activar la extensión de MySQL de PHP. Laragon → **Menú → PHP → Extensiones** → marca `pdo_mysql` y `mysqli`, y reinicia |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL está apagado. Laragon → **Iniciar todo** |
| `Unknown database 'lit_inmobiliaria'` | Falta el paso 3.4 (crear la base de datos) |
| `Access denied for user 'root'` | La contraseña de `DB_PASSWORD` en `.env` no es la de tu MySQL |
| Cambié `.env` y no hace efecto | `php artisan config:clear` y reinicia `php artisan serve` |
| Las tablas se crearon en SQLite / no veo mis tablas en MySQL | El `.env` no se guardó con `DB_CONNECTION=mysql`. Guárdalo (Ctrl + S) y repite `php artisan migrate:fresh --seed` |
| `Port 8000 is in use` | Usa otro puerto: `php artisan serve --port=8001` |
| "La página expiró" (419) al subir fotos | Las imágenes son muy pesadas. Máximo 4 MB cada una. Si persiste, sube `post_max_size` y `upload_max_filesize` en el `php.ini` de Laragon |
| El mapa se ve gris o sin calles | El diseño funciona sin internet, pero **los mapas necesitan internet** |
| Las propiedades salen sin foto después de `git pull` | `php artisan db:seed --class=PropiedadSeeder` |
| `composer` o `php` "no se reconoce" | Ver paso 1 (agregar Laragon al Path y reabrir VS Code) |

## 9. Trabajo en equipo (cuando se repartan los casos de uso)

Una rama por caso de uso: `git checkout -b cu8-nombre-del-caso`, trabajar, `git push -u origin cu8-nombre-del-caso`
y abrir un *Pull Request* hacia `main` en GitHub. Avisar en el grupo antes de modificar archivos compartidos
(`layouts/app.blade.php`, `panel.blade.php`, `AppServiceProvider.php`, `DatabaseSeeder.php`).
