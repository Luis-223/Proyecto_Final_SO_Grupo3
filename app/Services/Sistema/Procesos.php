<?php

namespace App\Services\Sistema;

/**
 * M1 / M2 — Procesos reales leídos de /proc/[pid]/stat, /proc/[pid]/status y /proc/[pid]/cmdline.
 *
 * El % de CPU de cada proceso se calcula igual que top: se toman DOS lecturas de
 * utime + stime separadas por una ventana corta y se divide entre el tiempo de CPU
 * que pasó en ese intervalo (100 % = un núcleo completo).
 */
final class Procesos
{
    /** Estados que muestra el resumen del enunciado. */
    public const ESTADOS = ['R', 'S', 'D', 'Z', 'T'];

    /**
     * Lista completa de procesos con % de CPU instantáneo.
     *
     * @return list<array<string, mixed>>
     */
    public static function instantanea(int $ventanaMs = 300): array
    {
        $cpuAntes = ProcReader::tiemposCpu()['cpu'] ?? ['total' => 0];
        $ticksAntes = self::ticksPorPid();

        usleep($ventanaMs * 1000);

        $cpuDespues = ProcReader::tiemposCpu()['cpu'] ?? ['total' => 0];
        $nucleos = ProcReader::cpuinfo()['logicos'];
        // jiffies que pasaron en UN núcleo durante la ventana
        $transcurrido = max(1, ($cpuDespues['total'] - $cpuAntes['total']) / $nucleos);

        $memTotal = ProcReader::memoria()['MemTotal'] ?? 1;
        $usuarios = self::usuarios();
        $lista = [];

        foreach (ProcReader::pids() as $pid) {
            $p = self::leer($pid, $usuarios);
            if ($p === null) {
                continue; // terminó entre la lectura de /proc y ahora
            }
            $delta = isset($ticksAntes[$pid]) ? max(0, $p['ticks'] - $ticksAntes[$pid]) : 0;
            $p['cpu'] = round(100 * $delta / $transcurrido, 1);
            $p['mem_pct'] = round(100 * $p['mem_kb'] / $memTotal, 1);
            unset($p['ticks']);
            $lista[] = $p;
        }

        return $lista;
    }

    /**
     * Cuenta cuántos procesos hay en cada estado (R, S, D, Z, T y otros).
     *
     * @param  list<array<string, mixed>>  $procesos
     * @return array<string, int>
     */
    public static function resumen(array $procesos): array
    {
        $conteo = array_fill_keys([...self::ESTADOS, 'I', 'otros'], 0);
        foreach ($procesos as $p) {
            $estado = $p['estado'];
            if (isset($conteo[$estado])) {
                $conteo[$estado]++;
            } else {
                $conteo['otros']++;
            }
        }

        return $conteo;
    }

    /**
     * Lee un proceso. Devuelve null si ya no existe.
     *
     * @param  array<int, string>  $usuarios  uid => nombre
     * @return array<string, mixed>|null
     */
    public static function leer(int $pid, ?array $usuarios = null): ?array
    {
        $stat = ProcReader::leer("/proc/{$pid}/stat");
        $status = ProcReader::leer("/proc/{$pid}/status");
        if ($stat === null || $status === null) {
            return null;
        }

        // El nombre (comm) va entre paréntesis y puede tener espacios: se corta en el ÚLTIMO ")".
        $abre = strpos($stat, '(');
        $cierra = strrpos($stat, ')');
        $nombre = substr($stat, $abre + 1, $cierra - $abre - 1);
        $campos = preg_split('/\s+/', trim(substr($stat, $cierra + 2)));
        // $campos[0] = campo 3 de stat (estado), $campos[1] = campo 4 (ppid), ...
        $estado = $campos[0] ?? '?';
        $ppid = (int) ($campos[1] ?? 0);
        $ticks = (int) ($campos[11] ?? 0) + (int) ($campos[12] ?? 0); // utime + stime
        $nice = (int) ($campos[16] ?? 0);
        $hilos = (int) ($campos[17] ?? 1);

        preg_match('/^Uid:\s+(\d+)/m', $status, $uid);
        preg_match('/^VmRSS:\s+(\d+)/m', $status, $rss);
        $uid = (int) ($uid[1] ?? -1);

        $cmdline = trim(str_replace("\0", ' ', (string) ProcReader::leer("/proc/{$pid}/cmdline")));
        $kernel = $pid === 2 || $ppid === 2;

        $usuarios ??= self::usuarios();

        return [
            'pid' => $pid,
            'ppid' => $ppid,
            'uid' => $uid,
            'usuario' => $usuarios[$uid] ?? (string) $uid,
            'estado' => $estado === 't' ? 'T' : $estado, // t = detenido por depurador
            'nice' => $nice,
            'mem_kb' => (int) ($rss[1] ?? 0),
            'hilos' => $hilos,
            'nombre' => $nombre,
            'comando' => mb_substr($cmdline !== '' ? $cmdline : "[{$nombre}]", 0, 300),
            'kernel' => $kernel,
            'ticks' => $ticks,
        ];
    }

    /** ¿El proceso sigue vivo? Un zombi (Z) ya terminó: solo espera que su padre lo recoja. */
    public static function existe(int $pid): bool
    {
        $p = self::leer($pid);

        return $p !== null && $p['estado'] !== 'Z';
    }

    /**
     * Comprueba que un PID sea realmente el proceso de prueba que lanzó la aplicación:
     * existe, pertenece al mismo usuario que ejecuta PHP (www-data) y su comando coincide.
     * Así se evita actuar sobre otro proceso si el kernel reutilizó el PID.
     */
    public static function esDePrueba(int $pid, string $tipo): bool
    {
        $p = self::leer($pid);
        if ($p === null || $p['uid'] !== self::uidPropio()) {
            return false;
        }

        return match ($tipo) {
            'sleep' => str_starts_with($p['comando'], '/usr/bin/sleep '),
            'carga_cpu' => str_contains($p['comando'], 'scripts/carga-cpu.php'),
            default => false,
        };
    }

    /** UID efectivo del proceso PHP actual (www-data bajo Apache). */
    public static function uidPropio(): int
    {
        preg_match('/^Uid:\s+\d+\s+(\d+)/m', (string) ProcReader::leer('/proc/self/status'), $m);

        return (int) ($m[1] ?? -1);
    }

    /**
     * uid => nombre leyendo /etc/passwd (sin ejecutar comandos).
     *
     * @return array<int, string>
     */
    public static function usuarios(): array
    {
        $mapa = [];
        foreach (file('/etc/passwd', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
            $c = explode(':', $linea);
            if (count($c) >= 3) {
                $mapa[(int) $c[2]] = $c[0];
            }
        }

        return $mapa;
    }

    /**
     * pid => utime + stime (una lectura).
     *
     * @return array<int, int>
     */
    private static function ticksPorPid(): array
    {
        $ticks = [];
        foreach (ProcReader::pids() as $pid) {
            $stat = ProcReader::leer("/proc/{$pid}/stat");
            if ($stat === null) {
                continue;
            }
            $campos = preg_split('/\s+/', trim(substr($stat, strrpos($stat, ')') + 2)));
            $ticks[$pid] = (int) ($campos[11] ?? 0) + (int) ($campos[12] ?? 0);
        }

        return $ticks;
    }
}
