<?php

namespace App\Support;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs the launcher in this application folder.
 * Windows uses PowerShell. Linux, macOS, BSD, and other Unix systems use sh.
 */
final class LocalRuntime
{
    /**
     * @var list<string>
     */
    private const COMMANDS = ['ensure', 'start', 'stop', 'share', 'unshare', 'install', 'uninstall'];

    /**
     * @return array{ok: bool, message: string}
     */
    public static function spawn(string $command): array
    {
        return self::execute($command, true);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function run(string $command): array
    {
        return self::execute($command, false);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function execute(string $command, bool $detach): array
    {
        $script = self::script($command);

        if (isset($script['message'])) {
            return ['ok' => false, 'message' => $script['message']];
        }

        try {
            $process = self::launcherProcess($script, $command, $detach);
            $process->run();
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'Peluncur tidak dapat dijalankan.'];
        }

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        if ($output !== '') {
            return ['ok' => $process->isSuccessful(), 'message' => $output];
        }

        if (! $process->isSuccessful()) {
            return [
                'ok' => false,
                'message' => $detach ? 'Peluncur tidak dapat dijalankan.' : 'Peluncur berhenti karena kesalahan.',
            ];
        }

        return [
            'ok' => true,
            'message' => $detach ? 'Perintah dikirim ke perangkat ini.' : 'Selesai.',
        ];
    }

    /**
     * @return array{path: string, family: string}|array{message: string}
     */
    private static function script(string $command): array
    {
        if (! in_array($command, self::COMMANDS, true)) {
            return ['message' => 'Perintah peluncur tidak dikenal.'];
        }

        $family = PHP_OS_FAMILY;
        $path = base_path(self::launcherFor($family));

        if (! is_file($path)) {
            return ['message' => 'Berkas peluncur tidak ditemukan.'];
        }

        return ['path' => $path, 'family' => $family];
    }

    public static function launcherFor(string $family): string
    {
        return $family === 'Windows'
            ? 'laravel-assets/scripts/runtime.ps1'
            : 'laravel-assets/scripts/runtime.sh';
    }

    /**
     * @param  array{path: string, family: string}  $script
     */
    private static function launcherProcess(array $script, string $command, bool $detach): Process
    {
        if ($script['family'] === 'Windows') {
            if ($detach) {
                $quoted = str_replace("'", "''", $script['path']);
                $power = "Start-Process -FilePath powershell.exe -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File','{$quoted}','-Command','{$command}' -WindowStyle Hidden";

                return new Process(
                    ['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $power],
                    base_path(),
                    null,
                    null,
                    30
                );
            }

            return new Process(
                ['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $script['path'], '-Command', $command],
                base_path(),
                null,
                null,
                120
            );
        }

        if ($detach) {
            return new Process(
                ['sh', '-c', 'sh "$1" "$2" >/dev/null 2>&1 &', 'arsip', $script['path'], $command],
                base_path(),
                null,
                null,
                30
            );
        }

        return new Process(
            ['sh', $script['path'], $command],
            base_path(),
            null,
            null,
            120
        );
    }
}
