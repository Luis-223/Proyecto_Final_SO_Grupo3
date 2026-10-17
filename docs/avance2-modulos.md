# Avance 2 — Lógica de los módulos M1 a M5

Este documento describe qué se agregó en cada módulo, qué archivos le corresponden a cada integrante, de dónde salen los datos y cómo se cumplen las reglas de seguridad del enunciado. Sirve de base para el manual técnico y para preparar la defensa.

## 1. Archivos por módulo (quién hace commit de qué)

Cada integrante sube **solo los archivos de su módulo** desde su rama, para que los commits demuestren su autoría.

| Módulo | Rama | Archivos |
|---|---|---|
| **M6 (base común)** | `feature/m6-integracion` | `app/Services/Sistema/ComandoSeguro.php` · `app/Services/Sistema/ProcReader.php` · `public/js/sysmonitor-comun.js` · `public/css/sysmonitor.css` · `resources/views/layouts/app.blade.php` · `routes/web.php` |
| **M1 Procesos** | `feature/m1-procesos` | `app/Services/Sistema/Procesos.php` · `app/Http/Controllers/ProcesosController.php` · `resources/views/modulos/procesos/index.blade.php` · `public/js/m1-procesos.js` · `scripts/carga-cpu.php` |
| **M2 CPU y Memoria** | `feature/m2-cpu-memoria` | `app/Http/Controllers/CpuMemoriaController.php` · `resources/views/modulos/cpu-memoria/index.blade.php` · `public/js/m2-cpu-memoria.js` |
| **M3 Planificación** | `feature/m3-planificacion` | `app/Services/Simulacion/Planificador.php` · `app/Http/Controllers/PlanificacionController.php` · `resources/views/modulos/planificacion/index.blade.php` · `public/js/m3-planificacion.js` · `tests/Unit/PlanificadorTest.php` |
| **M4 Interbloqueos** | `feature/m4-interbloqueos` | `app/Services/Simulacion/Banquero.php` · `app/Http/Controllers/InterbloqueosController.php` · `resources/views/modulos/interbloqueos/index.blade.php` · `public/js/m4-interbloqueos.js` · `tests/Unit/BanqueroTest.php` |
| **M5 Almacenamiento** | `feature/m5-almacenamiento` | `app/Services/Sistema/Almacenamiento.php` · `app/Http/Controllers/AlmacenamientoController.php` · `resources/views/modulos/almacenamiento/index.blade.php` |

**Orden de merge a `main`:** primero M6 (todos dependen de `ComandoSeguro`, `ProcReader`, `sysmonitor-comun.js` y las rutas), luego M1 (M2 usa `Procesos.php`), y después M2, M3, M4 y M5 en cualquier orden.

No hay migraciones nuevas: se usan las tablas `bitacora`, `procesos_prueba` y `simulaciones` del Avance 1.

## 2. Rutas nuevas

| Método y ruta | Controlador | Rol |
|---|---|---|
| `GET /api/procesos` | `ProcesosController@datos` | cualquiera |
| `POST /procesos/prueba` | `ProcesosController@lanzar` | Administrador |
| `POST /procesos/{pid}/senal` | `ProcesosController@senal` | Administrador |
| `POST /procesos/{pid}/renice` | `ProcesosController@renice` | Administrador |
| `GET /api/cpu-memoria` | `CpuMemoriaController@datos` | cualquiera |
| `POST /planificacion/simular` | `PlanificacionController@simular` | cualquiera |
| `POST /planificacion/guardar` | `PlanificacionController@guardar` | Administrador |
| `GET /planificacion/simulaciones` y `/{id}` | `PlanificacionController@lista` / `@mostrar` | cualquiera |
| `POST /interbloqueos/evaluar` | `InterbloqueosController@evaluar` | cualquiera |
| `POST /interbloqueos/solicitud` | `InterbloqueosController@solicitud` | cualquiera |
| `GET /almacenamiento?ruta=...` | `AlmacenamientoController@index` | cualquiera |

Las vistas piden los datos con `fetch` (usando la misma sesión y el token CSRF de la etiqueta `<meta>`). Las respuestas son JSON.

## 3. Fuentes de datos (archivos de /proc y comandos)

| Módulo | Dato | Fuente |
|---|---|---|
| M1 | PID, PPID, estado, nice, utime + stime | `/proc/[pid]/stat` (campos 3, 4, 14, 15, 19) |
| M1 | Usuario (UID) y memoria residente | `/proc/[pid]/status` (`Uid`, `VmRSS`) y `/etc/passwd` |
| M1 | Comando completo | `/proc/[pid]/cmdline` |
| M1 | % CPU por proceso | Δ(utime + stime) entre dos lecturas separadas 250–300 ms; 100 % = un núcleo (igual que `top`) |
| M1 | Procesos de prueba | `sleep 600` y `php scripts/carga-cpu.php 120` (consume CPU y termina solo) |
| M1 | Señales y prioridad | `kill -TERM/-KILL/-STOP/-CONT PID`, `renice VALOR -p PID` |
| M2 | Modelo y núcleos | `/proc/cpuinfo` |
| M2 | % de uso total y por núcleo | dos lecturas de `/proc/stat`: uso = 100 × (1 − Δocioso / Δtotal) |
| M2 | Carga y tiempo encendido | `/proc/loadavg`, `/proc/uptime` |
| M2 | RAM, caché y swap | `/proc/meminfo` (usada = MemTotal − MemFree − Buffers − Cached − SReclaimable, como `free`) |
| M5 | Discos, particiones y tabla MBR/GPT | `lsblk -J -o NAME,SIZE,TYPE,FSTYPE,MOUNTPOINT,PTTYPE` |
| M5 | Uso de sistemas de archivos | `df -h --output=...` sin tmpfs, devtmpfs, squashfs, efivarfs ni overlay |
| M5 | Montajes persistentes | `findmnt --fstab -J` (si falla, se lee `/etc/fstab`) |
| M5 | Usuarios humanos y del sistema | `/etc/passwd`: humano = UID ≥ 1000, distinto de 65534 y con shell de login |
| M5 | Sesiones activas | `who` |
| M5 | Explorador | `scandir` + `lstat` dentro de `/var/www` |

M3 y M4 son simulaciones: no leen el sistema.

## 4. Seguridad (sección 6 del enunciado)

1. **Sin concatenar datos del usuario.** `ComandoSeguro` arma cada línea con la ruta fija del ejecutable y pasa **cada** argumento por `escapeshellarg()`. El PID se valida con `ComandoSeguro::pidValido()`: rechaza `1`, `0`, `12; ls`, `1e3`, `-5`, espacios, etc.
2. **Lista blanca.** Solo `ps`, `kill`, `renice`, `sleep`, `php`, `lsblk`, `df`, `findmnt` y `who`. Lanzar en segundo plano solo se permite para `sleep` y `php`, y los argumentos son fijos (el usuario solo elige el *tipo* de proceso).
3. **Solo procesos de prueba.** Antes de enviar una señal o renice se comprueba que el PID: está en `procesos_prueba` como activo, sigue vivo, pertenece al mismo UID que ejecuta PHP (`www-data`) y su comando es exactamente el que lanzó la app. Esto evita actuar sobre otro proceso si el kernel reutilizó el PID. `www-data` no tiene `sudo`; por eso solo puede **subir** el nice, y la app lo explica si se intenta bajar.
4. **Rol y bitácora.** Lanzar, señales, renice y guardar simulaciones están en `middleware('admin')`. Cada intento queda en `bitacora` con resultado `exito`, `error` o `rechazado`: `LANZAR_PRUEBA`, `SIGTERM`, `SIGKILL`, `SIGSTOP`, `SIGCONT`, `RENICE`, `GUARDAR_SIMULACION`.

Límites adicionales: máximo 5 procesos de prueba vivos, 30 acciones por minuto (`throttle`), la carga de CPU termina sola a los 120 s, y el explorador resuelve las rutas con `realpath()` para que ni `..` ni los enlaces simbólicos salgan de `/var/www`.

## 5. Casos de prueba para la demostración

- **M3:** botón *Ejemplo* → espera promedio FCFS 8.20 · SJF 5.60 · SRTF 5.20 · RR (q = 2) 10.60 · Prioridades 7.80.
- **M4:** *Ejemplo seguro* (Silberschatz) → secuencia ⟨P1, P3, P4, P0, P2⟩. Solicitud de P1 (1, 0, 2) → se concede. *Ejemplo con interbloqueo* → ciclo P0 → B → P1 → C → P2 → A → P0 y sugerencia de abortar P0.
- **M1:** lanzar *sleep* → aparece en estado S con la etiqueta "prueba"; SIGSTOP → T; SIGCONT → S; renice 10 → nice 10; SIGTERM → desaparece. Con la cuenta Observador no aparecen los botones.
- **Pruebas unitarias** (en una máquina con dependencias de desarrollo): `php artisan test --filter="PlanificadorTest|BanqueroTest"`.
