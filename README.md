# SysMonitor Web

Aplicación web para observar y administrar, desde el navegador, el sistema operativo Linux donde está instalada. Lee información real del kernel desde `/proc` (procesos, CPU, memoria), ejecuta comandos del sistema de forma controlada (discos, particiones, usuarios) e incluye simuladores de planificación de CPU y de interbloqueos.

Proyecto del curso de Sistemas Operativos.

**URL pública:** _pendiente (Avance 2)_

## Integrantes y módulos

El grupo tiene **5 integrantes** y cubre los **6 módulos base**. Un integrante se hace cargo de dos módulos (M2 y M5), que comparten la misma técnica: lectura de `/proc` y comandos de solo lectura.

| # | Integrante | Carné | Usuario GitHub | Módulo(s) | Rama |
|---|---|---|---|---|---|
| 1 | _Nombre Apellido_ | _0000-00-00000_ | `@usuario` | **M1 — Procesos** | `feature/m1-procesos` |
| 2 | _Nombre Apellido_ | _0000-00-00000_ | `@usuario` | **M2 — CPU y Memoria** + **M5 — Almacenamiento** | `feature/m2-cpu-memoria`, `feature/m5-almacenamiento` |
| 3 | _Nombre Apellido_ | _0000-00-00000_ | `@usuario` | **M3 — Planificación de CPU** | `feature/m3-planificacion` |
| 4 | _Nombre Apellido_ | _0000-00-00000_ | `@usuario` | **M4 — Interbloqueos** | `feature/m4-interbloqueos` |
| 5 | _Nombre Apellido_ | _0000-00-00000_ | `@usuario` | **M6 — Integración y Bitácora** (líder técnico) | `feature/m6-integracion` |

| Módulo | Qué hace | Fuente de datos |
|---|---|---|
| M1 Procesos | Tabla y árbol de procesos, procesos de prueba, señales y `renice` | `/proc/[pid]/stat`, `/proc/[pid]/status`, `ps`, `kill`, `renice` |
| M2 CPU y Memoria | Uso de CPU, carga, RAM y swap con gráficas en vivo | `/proc/cpuinfo`, `/proc/stat`, `/proc/loadavg`, `/proc/meminfo`, `/proc/uptime` |
| M3 Planificación | Simulador FCFS, SJF, SRTF, Round Robin y Prioridades con Gantt | Simulado, guardado en SQLite |
| M4 Interbloqueos | Algoritmo del banquero y grafo de asignación de recursos | Simulado |
| M5 Almacenamiento | Discos, particiones, sistemas de archivos, usuarios y explorador | `lsblk`, `df -h`, `findmnt`, `/etc/passwd`, `who` |
| M6 Integración | Login con roles, layout, dashboard, bitácora y despliegue | Laravel + SQLite + Apache |

## Tecnologías

Debian 12 · Apache 2 · PHP 8.2 · Laravel 12 · SQLite · Blade + Bootstrap 5 · Chart.js

## Estructura del proyecto

```
app/
├── Http/Controllers/         # Un controlador por módulo
│   ├── Auth/LoginController  # M6
│   ├── ProcesosController    # M1
│   ├── CpuMemoriaController  # M2
│   ├── PlanificacionController   # M3
│   ├── InterbloqueosController   # M4
│   ├── AlmacenamientoController  # M5
│   └── DashboardController, BitacoraController  # M6
├── Http/Middleware/EsAdministrador.php  # rol Administrador
├── Models/                   # User (rol), Bitacora, ProcesoPrueba, Simulacion
└── Services/Sistema/
    ├── ComandoSeguro.php     # ÚNICA puerta al shell: lista blanca + escapeshellarg
    └── ProcReader.php        # lectura de /proc
apache/sysmonitor.conf        # VirtualHost
database/migrations/          # users.rol, bitacora, procesos_prueba, simulaciones
docs/                         # guías, mockups y evidencias
resources/views/
├── layouts/app.blade.php     # layout común (menú lateral)
└── modulos/<modulo>/         # vistas de cada módulo
routes/web.php                # rutas agrupadas por módulo
scripts/setup-vm.sh           # instalación en Debian 12
```

## Instalación en Debian 12

Guía completa: [`docs/avance1-vm-debian.md`](docs/avance1-vm-debian.md).

```bash
sudo bash scripts/setup-vm.sh https://github.com/USUARIO/REPO.git
```

Requisitos: Debian 12, Apache 2, PHP 8.2 con `pdo_sqlite`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`, Composer 2 y Git.

## Seguridad

- Ningún dato del usuario se concatena en un comando: todo pasa por `App\Services\Sistema\ComandoSeguro`, que usa una lista blanca de comandos y `escapeshellarg()` en cada argumento.
- Las señales y `renice` solo se aplican a procesos de prueba lanzados por la aplicación (tabla `procesos_prueba`, usuario `www-data`). `www-data` no tiene `sudo`.
- Las acciones administrativas requieren rol Administrador (`middleware('admin')`) y quedan en la bitácora.
- No hay registro público de usuarios. `.env` y la base `.sqlite` nunca se suben al repositorio.

## Flujo de trabajo en GitHub

1. `main` está protegida: nadie hace push directo.
2. Cada integrante trabaja en la rama de su módulo:
   ```bash
   git checkout main && git pull
   git checkout -b feature/m1-procesos
   # ...cambios...
   git add . && git commit -m "M1: tabla de procesos desde /proc"
   git push -u origin feature/m1-procesos
   ```
3. Se abre un Pull Request hacia `main`; lo revisa y aprueba **otro** integrante; M6 hace el merge.
4. Las tareas se asignan como Issues (plantilla “Tarea de módulo”) en el tablero del proyecto.
5. Mínimo 10 commits significativos por integrante, repartidos a lo largo del semestre. Los mensajes empiezan con el código del módulo (`M3: algoritmo SRTF`).

## Entregas

| Entrega | Contenido | Estado |
|---|---|---|
| Avance 1 | Integrantes y módulos, repositorio Laravel, mockups, VM Debian con Apache y PHP | ✅ |
| Avance 2 | 50 % de cada módulo en `main`, login y bitácora, dominio y HTTPS | ⏳ |
| Final | URL pública, `v1.0`, manuales, video y presentación | ⏳ |

## Mockups

Los bocetos de cada pantalla están en [`docs/mockups/`](docs/mockups/).

## Capturas de pantalla

_Se agregan en el Avance 2._
