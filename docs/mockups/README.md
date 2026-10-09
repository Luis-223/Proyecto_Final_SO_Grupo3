# Mockups — Avance 1

Los bocetos de todas las pantallas están en un lienzo de diseño compartido (Claude Design):
**[SysMonitor Web — Mockups](https://claude.ai/artifact/Bfodjs29SttHbbTcK5HKga)**

Desde el lienzo se exporta cada pantalla como PNG o PDF (menú Compartir → Exportar). Las imágenes exportadas se guardan en esta carpeta con los nombres de la tabla.

| Archivo | Pantalla | Módulo | Qué muestra |
|---|---|---|---|
| `01-login.png` | Inicio de sesión | M6 | Formulario sin registro público, estado de error, límite de intentos |
| `02-dashboard.png` | Dashboard | M6 | CPU, RAM, disco y carga; gráfica de CPU; procesos por estado; últimas acciones |
| `03-procesos-tabla.png` | Procesos — tabla | M1 | Resumen R/S/D/Z/T, búsqueda y orden, procesos de prueba, panel de señales y renice |
| `04-procesos-arbol.png` | Procesos — árbol | M1 | Jerarquía padre-hijo desde PID 1 |
| `05-cpu-memoria.png` | CPU y Memoria | M2 | Modelo, uptime, carga, gráfica en vivo por núcleo, RAM/caché/swap, top 5 |
| `06-planificacion.png` | Planificación de CPU | M3 | Ingreso de procesos, algoritmo y quantum, Gantt (RR q=2), tiempos y comparativa |
| `07-interbloqueos.png` | Interbloqueos | M4 | Matrices, Necesidad, paso a paso del banquero, solicitud, grafo con ciclo |
| `08-almacenamiento.png` | Almacenamiento | M5 | lsblk, df -h con indicador, fstab, usuarios humanos/sistema, who, explorador |
| `09-bitacora.png` | Bitácora | M6 | Filtros, registros con resultado e IP, paginación |

## Datos de ejemplo

Los números de M3 y M4 son correctos para los datos mostrados y sirven como casos de prueba:

- **M3** — procesos P1–P5 con (llegada, ráfaga, prioridad) = (0,5,3), (1,3,1), (2,8,4), (3,6,2), (4,2,5). Espera promedio: FCFS 8.20 · SJF 5.60 · SRTF 5.20 · RR q=2 10.60 · Prioridades 7.80.
- **M4** — ejemplo clásico de Silberschatz (5 procesos, recursos A=10, B=5, C=7, Disponible 3,3,2). Secuencia segura ⟨P1, P3, P4, P0, P2⟩. La solicitud (1,0,2) de P1 se concede.

## Estilo común

| Elemento | Valor |
|---|---|
| Tipografías | IBM Plex Sans (texto) · IBM Plex Mono (PID, rutas, valores) |
| Fondo / tarjetas | `#f4f5f2` / `#ffffff` con borde `#dfe3de` |
| Menú lateral | `#15201d` |
| Acento | `#1a7a5c` |
| Estados de proceso | R `#1a7a5c` · S `#5f6965` · D `#9a5a0a` · Z `#b93a26` · T `#6a4fa3` |

Los mismos valores están en `public/css/sysmonitor.css`.
