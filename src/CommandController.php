<?php

declare(strict_types=1);

namespace davekok\webpackage;

use Throwable;

class CommandController
{
    public function __construct(
        public readonly string $command
    ) {}

    public function handle(array $args, int $offset = 0): noreturn
    {
        try {
            switch ($args[$offset] ?? "") {
                case "build":
                    $this->handleBuildOptions($args, $offset + 1);
                case "install":
                    $this->handleInstallOptions($args, $offset + 1);
                case "help":
                    switch ($args[$offset + 1] ?? "") {
                        case "build":
                            $this->printBuildHelp();
                    }
                default:
                    $this->printHelp();
            }
        } catch (Throwable $throwable) {
            echo "error: {$throwable->getMessage()}\n";
        }
        exit();
    }

    public function printHelp(): noreturn
    {
        exit(<<<HELP
            Usage: {$this->command} [SUBCOMMAND]...

            List of subcommands:
                build      to build a webpackage
                install    install webpackage
                help       print this screen

            HELP);
    }

    public function handleBuildOptions(array $args, int $offset = 0): noreturn
    {
        $out      = null;
        $strip    = null;
        $domain   = null;
        $cert     = null;
        $buildKey = null;
        $encoding = null;
        $files    = [];

        $argc = count($args);
        if ($argc === $offset) {
            $this->printBuildHelp();
        }
        for ($i = $offset; $i < $argc; ++$i) {
            $c = $i;
            if ($args[$c][0] === "-") {
                if ($args[$c][1] !== "-") {
                    $argl = strlen($args[$c]);
                    for ($j = 1; $j < $argl; ++$j) {
                        switch ($args[$c][$j]) {
                            case "o":
                                $out = $args[++$i];
                                break;
                            case "s":
                                $strip = $args[++$i];
                                break;
                            case "d":
                                $domain = $args[++$i];
                                break;
                            case "c":
                                $cert = $args[++$i];
                                break;
                            case "k":
                                $key = $args[++$i];
                                break;
                            case "e":
                                $encoding = $args[++$i];
                                break;
                            case "h":
                            case "?":
                                $this->printBuildHelp();
                        }
                    }
                    continue;
                }
                if ($args[$c] === "--out") {
                    $out = $args[++$i];
                    continue;
                }
                if ($args[$c] === "--strip") {
                    $strip = $args[++$i];
                    continue;
                }
                if ($args[$c] === "--domain") {
                    $domain = $args[++$i];
                    continue;
                }
                if ($args[$c] === "--cert") {
                    $cert = $args[++$i];
                    continue;
                }
                if ($args[$c] === "--key") {
                    $key = new WebPackageBuildKey($args[++$i]);
                    continue;
                }
                if ($args[$c] === "--encoding") {
                    $encoding = $args[++$i];
                    continue;
                }
                if ($args[$c] === "--help") {
                    $this->printBuildHelp();
                }
            }
            $files[] = realpath($args[$c]);
        }
        (new BuildCommand(
            buildKey:    $buildKey ?? new WebPackageBuildKey(),
            domain:      $domain,
            certificate: $cert,
            encoding:    $encoding,
            out:         $out,
            strip:       $strip,
            files:       $files,
        ))->build();
        exit();
    }

    public function printBuildHelp(): noreturn
    {
        exit(<<<HELP
            Usage: {$this->command} build [OPTIONS]... [FILES]...

            Build a webpackage containing the listed files.

            You may also specify directories in which case they are crawled.

            List of options:
              -o, --out FILE    the file to write the webpackage to
              -s, --strip PATH  strip PATH from files
              -k, --key FILE    the webpackage build key (see server log)
              -c, --cert FILE   add server certificate to webpackage
              -b, --br          use brotli algorithm to compress all files
              -d, --deflate     use deflate algorithm to compress all files
              -h, -?, --help    display this screen

            HELP);
    }
}
