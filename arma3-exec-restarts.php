<?php

const RESTART_TRIGGER_FILENAME = '/tmp/arma3server-webrestart-command';
const ARMA3_PATH = '/home/arma3server/arma3';
const SLEEP = 2;
const USER = 'arma3server';


if (get_current_user() !== USER) {
        echo "run me as " . USER;
        exit(1);
}

chdir(ARMA3_PATH);

while (true) {
        sleep(SLEEP);
        if (!file_exists(RESTART_TRIGGER_FILENAME)) {
                continue;
        }

        $contents = file_get_contents(RESTART_TRIGGER_FILENAME);
        file_put_contents(RESTART_TRIGGER_FILENAME, '');

        $lines = array_filter(explode("\n", $contents));
        $commands = array_filter(array_map('json_decode', $lines));

        $a3ups = [];
        foreach ($commands as $command) {
                $port = intval(isset($command->port) ? $command->port : 0);
                $template = isset($command->template) ? trim($command->template) : '';
                if (($port === 0) || ($template && (strpos('/', $template) !== false))) {
                        syslog(LOG_ALERT, 'invalid parameters from arma3server-webrestart: ' . json_encode($command));
                        break;
                }
                $a3ups[$port] = sprintf("./a3up.sh %s %s & ", $port, $template);
        }
        foreach ($a3ups as $a3up) {
                echo sprintf("executing %s in %s…\n", $a3up, getcwd());
                $out = shell_exec($a3up);
                echo "\n" . str_replace("\n", "\n\t", $out) . "\n";
        }
}
