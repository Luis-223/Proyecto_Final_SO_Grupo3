<?php

namespace App\Services\Sistema;

use RuntimeException;

/**
 * M5 — Discos, particiones, sistemas de archivos, usuarios y explorador de solo lectura.
 * Los comandos pasan por ComandoSeguro con argumentos FIJOS: nada viene del usuario.
 */
final class Almacenamiento
{
    /** Única carpeta que se puede explorar (y todo lo que haya debajo). */
    public const RAIZ_EXPLORADOR = '/var/www';

    /**
     * lsblk en JSON => filas planas con nivel de anidación (disco → partición → LVM...).
     *
     * @return array{filas: list<array<string, mixed>>, tablas: array<string, string>}
     */
    public static function bloques(): array
    {
        $json = ComandoSeguro::ejecutar('lsblk', ['-J', '-o', 'NAME,SIZE,TYPE,FSTYPE,MOUNTPOINT,PTTYPE']);
        $datos = json_decode($json, true)['blockdevices'] ?? [];

        $filas = [];
        $tablas = [];
        $recorrer = function (array $dispositivos, int $nivel) use (&$recorrer, &$filas, &$tablas) {
            foreach ($dispositivos as $d) {
                if ($d['type'] === 'disk' && ($d['size'] ?? '0B') === '0B') {
                    continue; // zram o dispositivos vacíos
                }
                if ($d['type'] === 'disk' && ! empty($d['pttype'])) {
                    $tablas[$d['name']] = self::nombreTabla($d['pttype']);
                }
                $filas[] = [
                    'nombre' => $d['name'],
                    'nivel' => $nivel,
                    'tamano' => $d['size'] ?? '',
                    'tipo' => $d['type'] ?? '',
                    'fs' => $d['fstype'] ?? null,
                    'montaje' => $d['mountpoint'] ?? null,
                ];
                $recorrer($d['children'] ?? [], $nivel + 1);
            }
        };
        $recorrer($datos, 0);

        return ['filas' => $filas, 'tablas' => $tablas];
    }

    /** dos => MBR, gpt => GPT */
    public static function nombreTabla(string $pttype): string
    {
        return match (strtolower($pttype)) {
            'dos', 'mbr' => 'MBR',
            'gpt' => 'GPT',
            default => strtoupper($pttype),
        };
    }

    /**
     * df -h de los sistemas de archivos reales (sin tmpfs, devtmpfs, etc.).
     *
     * @return list<array<string, mixed>>
     */
    public static function sistemasArchivos(): array
    {
        $salida = ComandoSeguro::ejecutar('df', [
            '-h', '--output=source,fstype,size,used,avail,pcent,target',
            '-x', 'tmpfs', '-x', 'devtmpfs', '-x', 'squashfs', '-x', 'efivarfs', '-x', 'overlay',
        ]);

        $lineas = array_slice(explode("\n", trim($salida)), 1); // sin encabezado
        $filas = [];
        foreach ($lineas as $linea) {
            $c = preg_split('/\s+/', trim($linea), 7);
            if (count($c) < 7) {
                continue;
            }
            $filas[] = [
                'origen' => $c[0], 'tipo' => $c[1], 'tamano' => $c[2], 'usado' => $c[3],
                'libre' => $c[4], 'pct' => (int) rtrim($c[5], '%'), 'montaje' => $c[6],
            ];
        }

        return $filas;
    }

    /**
     * Puntos de montaje persistentes de /etc/fstab (con findmnt --fstab; si falla, se lee el archivo).
     *
     * @return list<array<string, string>>
     */
    public static function fstab(): array
    {
        try {
            $json = ComandoSeguro::ejecutar('findmnt', ['--fstab', '-J', '-o', 'SOURCE,TARGET,FSTYPE,OPTIONS,FREQ,PASSNO']);
            $datos = json_decode($json, true)['filesystems'] ?? null;
            if (is_array($datos)) {
                return array_map(fn ($f) => [
                    'dispositivo' => (string) ($f['source'] ?? ''),
                    'montaje' => (string) ($f['target'] ?? ''),
                    'tipo' => (string) ($f['fstype'] ?? ''),
                    'opciones' => (string) ($f['options'] ?? ''),
                    'dump_pass' => ($f['freq'] ?? '0').' / '.($f['passno'] ?? '0'),
                ], $datos);
            }
        } catch (RuntimeException) {
            // findmnt devuelve error si fstab está vacío; se intenta leer el archivo
        }

        $filas = [];
        foreach (file('/etc/fstab', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }
            $c = preg_split('/\s+/', $linea);
            $filas[] = [
                'dispositivo' => $c[0] ?? '', 'montaje' => $c[1] ?? '', 'tipo' => $c[2] ?? '',
                'opciones' => $c[3] ?? '', 'dump_pass' => ($c[4] ?? '0').' / '.($c[5] ?? '0'),
            ];
        }

        return $filas;
    }

    /**
     * /etc/passwd separado en humanos (UID ≥ 1000 con shell de login) y sistema.
     *
     * @return array{humanos: list<array<string, mixed>>, sistema: list<array<string, mixed>>}
     */
    public static function usuarios(): array
    {
        $resultado = ['humanos' => [], 'sistema' => []];
        foreach (file('/etc/passwd', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
            $c = explode(':', $linea);
            if (count($c) < 7) {
                continue;
            }
            $u = ['usuario' => $c[0], 'uid' => (int) $c[2], 'home' => $c[5], 'shell' => $c[6]];
            $sinLogin = (bool) preg_match('#/(nologin|false|sync)$#', $c[6]);
            $humano = $u['uid'] >= 1000 && $u['uid'] !== 65534 && ! $sinLogin;
            $resultado[$humano ? 'humanos' : 'sistema'][] = $u;
        }

        return $resultado;
    }

    /**
     * Sesiones activas con who.
     *
     * @return list<array<string, string>>
     */
    public static function sesiones(): array
    {
        $filas = [];
        foreach (explode("\n", trim(ComandoSeguro::ejecutar('who'))) as $linea) {
            // usuario  terminal  2026-10-10 15:02 (203.0.113.5)
            if (preg_match('/^(\S+)\s+(\S+)\s+(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\s*(?:\((.*)\))?/', $linea, $m)) {
                $filas[] = ['usuario' => $m[1], 'terminal' => $m[2], 'desde' => $m[3], 'origen' => $m[4] ?? 'local'];
            }
        }

        return $filas;
    }

    /**
     * Lista una carpeta dentro de RAIZ_EXPLORADOR. La ruta pedida se resuelve con realpath()
     * y se rechaza si queda fuera de la raíz (evita ../../etc y enlaces simbólicos hacia afuera).
     *
     * @return array{ruta: string, padre: ?string, entradas: list<array<string, mixed>>}
     */
    public static function explorar(?string $relativa): array
    {
        $raiz = realpath(self::RAIZ_EXPLORADOR) ?: self::RAIZ_EXPLORADOR;
        $pedida = $raiz.'/'.ltrim((string) $relativa, '/');
        $ruta = realpath($pedida);

        if ($ruta === false || ! is_dir($ruta) || ($ruta !== $raiz && ! str_starts_with($ruta, $raiz.'/'))) {
            $ruta = $raiz;
        }

        $usuarios = Procesos::usuarios();
        $grupos = self::grupos();
        $entradas = [];
        foreach (scandir($ruta) ?: [] as $nombre) {
            if ($nombre === '.' || $nombre === '..') {
                continue;
            }
            $completo = $ruta.'/'.$nombre;
            $info = @lstat($completo);
            if ($info === false) {
                continue;
            }
            $esDir = is_dir($completo) && ! is_link($completo);
            $entradas[] = [
                'nombre' => $nombre,
                'dir' => $esDir,
                'enlace' => is_link($completo),
                'permisos' => self::permisos($info['mode']),
                'propietario' => $usuarios[$info['uid']] ?? (string) $info['uid'],
                'grupo' => $grupos[$info['gid']] ?? (string) $info['gid'],
                'tamano' => $esDir ? null : $info['size'],
                'relativa' => ltrim(substr($completo, strlen($raiz)), '/'),
            ];
        }
        // Carpetas primero, luego por nombre
        usort($entradas, fn ($a, $b) => [$b['dir'], $a['nombre']] <=> [$a['dir'], $b['nombre']]);

        return [
            'ruta' => $ruta,
            'padre' => $ruta === $raiz ? null : ltrim(substr(dirname($ruta), strlen($raiz)), '/'),
            'entradas' => $entradas,
        ];
    }

    /** Modo numérico => "drwxr-xr-x" */
    public static function permisos(int $modo): string
    {
        $tipo = match ($modo & 0xF000) {
            0x4000 => 'd', 0xA000 => 'l', 0x2000 => 'c', 0x6000 => 'b', 0x1000 => 'p', 0xC000 => 's',
            default => '-',
        };
        $bits = '';
        foreach ([0400, 0200, 0100, 040, 020, 010, 04, 02, 01] as $i => $bit) {
            $bits .= ($modo & $bit) ? 'rwx'[$i % 3] : '-';
        }
        // setuid / setgid / sticky
        if ($modo & 04000) {
            $bits[2] = $bits[2] === 'x' ? 's' : 'S';
        }
        if ($modo & 02000) {
            $bits[5] = $bits[5] === 'x' ? 's' : 'S';
        }
        if ($modo & 01000) {
            $bits[8] = $bits[8] === 'x' ? 't' : 'T';
        }

        return $tipo.$bits;
    }

    /** Bytes => "4.0 K", "12 M"… como ls -lh */
    public static function legible(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        $unidades = ['B', 'K', 'M', 'G', 'T'];
        $i = 0;
        $valor = (float) $bytes;
        while ($valor >= 1024 && $i < count($unidades) - 1) {
            $valor /= 1024;
            $i++;
        }

        return $i === 0 ? "{$bytes} B" : number_format($valor, $valor < 10 ? 1 : 0).' '.$unidades[$i];
    }

    /** @return array<int, string> gid => nombre desde /etc/group */
    private static function grupos(): array
    {
        $mapa = [];
        foreach (file('/etc/group', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
            $c = explode(':', $linea);
            if (count($c) >= 3) {
                $mapa[(int) $c[2]] = $c[0];
            }
        }

        return $mapa;
    }
}
