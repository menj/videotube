<?php
final class MediaCommand {
    /** Execute argument arrays without a shell, with bounded time and output. */
    public static function run(array $args, int $timeout = 120): string {
        $process = proc_open($args, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start media processor.');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $output = ''; $start = microtime(true); $code = -1;
        try {
            do {
                $output .= stream_get_contents($pipes[1]);
                stream_get_contents($pipes[2]); // Drain stderr without exposing paths/details to visitors.
                if (strlen($output) > 65536 || microtime(true) - $start > $timeout) {
                    throw new RuntimeException('Media processing limit exceeded.');
                }
                $status = proc_get_status($process);
                if (!$status['running']) { $code = $status['exitcode']; break; }
                usleep(10000);
            } while (true);
            $output .= stream_get_contents($pipes[1]);
        } finally {
            $status = proc_get_status($process);
            if ($status['running']) proc_terminate($process, 9);
            fclose($pipes[1]); fclose($pipes[2]);
            proc_close($process);
        }
        if ($code !== 0) throw new RuntimeException('Media processing failed.');
        return trim($output);
    }
}
