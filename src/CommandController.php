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
                help       print this screen

            HELP);
    }

    public function handleBuildOptions(array $args, int $offset = 0): noreturn
    {
        $out      = null;
        $strip    = null;
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
                            case "g":
                                $encoding = "gzip";
                                break;
                            case "c":
                                $encoding = "compress";
                                break;
                            case "d":
                                $encoding = "deflate";
                                break;
                            case "b":
                                $encoding = "br";
                                break;
                            case "h":
                            case "?":
                                $this->printBuildHelp();
                        }
                    }
                    continue;
                }
                if ($args[$c] === "--out") {
                    $out = $args[$c+1];
                    continue;
                }
                if ($args[$c] === "--strip") {
                    $strip = $args[$c+1];
                    continue;
                }
                if ($args[$c] === "--gzip") {
                    $encoding = "gzip";
                    continue;
                }
                if ($args[$c] === "--compress") {
                    $encoding = "compress";
                    continue;
                }
                if ($args[$c] === "--deflate") {
                    $encoding = "deflate";
                    continue;
                }
                if ($args[$c] === "--br") {
                    $encoding = "br";
                    continue;
                }
                if ($args[$c] === "--help") {
                    $this->printBuildHelp();
                }
            }
            $files[] = realpath($args[$c]);
        }
        (new BuildCommand(out: $out, strip: $strip, encoding: $encoding, files: $files))->build();
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
              -b, --br          use brotli algorithm to compress all files
              -g, --gzip        use gzip algorithm to compress all files
              -d, --deflate     use deflate algorithm to compress all files
              -c, --compress    use compress algorithm to compress all files
              -h, -?, --help    display this screen

            HELP);
    }
}
