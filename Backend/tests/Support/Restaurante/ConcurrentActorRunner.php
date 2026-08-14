<?php

namespace Tests\Support\Restaurante;

use Symfony\Component\Process\Process;

/**
 * Lanza N actores OS independientes (cada uno con su propio PHP + PDO).
 * No usa una transacción envolvente del test padre.
 */
final class ConcurrentActorRunner
{
    /**
     * @param  array<int, array<string, mixed>>  $actorPayloads  payloads JSON por actor
     * @return array<int, array{exit_code:int, stdout:string, stderr:string, json:?array}>
     */
    public static function run(string $scriptRelativeToBackend, array $actorPayloads, int $timeoutSeconds = 60): array
    {
        $backend = dirname(__DIR__, 3); // tests/Support/Restaurante → Backend
        $script = $backend . '/' . ltrim($scriptRelativeToBackend, '/');
        $php = PHP_BINARY;

        $workdir = sys_get_temp_dir() . '/sp_rest_conc_' . bin2hex(random_bytes(8));
        mkdir($workdir, 0700, true);

        $barrier = $workdir . '/barrier';
        file_put_contents($barrier, '0');

        $processes = [];
        $outFiles = [];

        foreach ($actorPayloads as $i => $payload) {
            $payload['barrier_file'] = $barrier;
            $payload['expected_actors'] = count($actorPayloads);
            $in = $workdir . "/in_{$i}.json";
            $out = $workdir . "/out_{$i}.json";
            file_put_contents($in, json_encode($payload, JSON_THROW_ON_ERROR));
            $outFiles[$i] = $out;

            $processes[$i] = new Process([$php, $script, $in, $out]);
            $processes[$i]->setWorkingDirectory($backend);
            $processes[$i]->setTimeout($timeoutSeconds);
        }

        foreach ($processes as $p) {
            $p->start();
        }

        $results = [];
        foreach ($processes as $i => $p) {
            $p->wait();
            $json = null;
            if (is_file($outFiles[$i])) {
                $raw = file_get_contents($outFiles[$i]);
                $json = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
            }
            $results[$i] = [
                'exit_code' => $p->getExitCode() ?? 1,
                'stdout' => $p->getOutput(),
                'stderr' => $p->getErrorOutput(),
                'json' => is_array($json) ? $json : null,
            ];
        }

        // cleanup best-effort
        foreach (glob($workdir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($workdir);

        return $results;
    }
}
