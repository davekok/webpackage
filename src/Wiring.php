<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\kernel\UrlFactory;
use davekok\parser\RulesBagFactory;
use davekok\system\NoSuchParameterWiringException;
use davekok\system\NoSuchServiceWiringException;
use davekok\system\NoSuchSetupServiceWiringException;
use davekok\system\Runnable;
use davekok\system\Wireable;
use davekok\system\WiringException;
use davekok\system\WiringInterface;
use davekok\system\Wirings;
use ReflectionClass;

class Wiring implements WiringInterface
{
    public function __construct(
        private readonly string $serviceDirectory = "file:/srv/",
        private readonly string $fileName         = "webpackage.wpk",
        private readonly string $serverKey        = "server.pem",
        private readonly string $clientKey        = "client.pem",
    ) {}

    public function infoParameters(): array
    {
        return [
            "service-directory" => "Set service directory in which the webpackage, server-key and"
                . " client-key are stored, defaults to 'file:/srv'.",
            "client-key"        => "Set the name of the client key file, defaults to 'client.pem'.",
            "server-key"        => "Set the name of the server key file, defaults to 'server.pem'.",
            "file-name"         => "Set the file name of the web package file, defaults to 'webpackage.wpk'.",
        ];
    }

    public function setParameter(string $key, string|int|float|bool|null $value): void
    {
        match ($key) {
            "service-directory" => $this->setServiceDirectory($value),
            "file-name"         => $this->fileName         = $value,
            "client-key"        => $this->clientKey        = $value,
            "server-key"        => $this->serverKey        = $value,
            default             => throw new NoSuchParameterWiringException($key),
        };
    }

    public function getParameter(string $key): string|int|float|bool|null
    {
        return match ($key) {
            "service-directory" => $this->serviceDirectory,
            "file-name"         => $this->fileName,
            "server-key"        => $this->serverKey,
            "client-key"        => $this->clientKey,
            default             => throw new NoSuchParameterWiringException($key),
        };
    }

    public function setup(Wirings $wirings): void
    {
        $wirings->get("http")->setupService("router")->mount("/", new class() implements Wireable {
            public function wire(Wirings $wirings): HttpRequestHandler
            {
                return new WebPackageRequestHandler();
            }
        })
    }

    public function setupService(string $key): object
    {
        return match ($key) {
            default => throw new NoSuchSetupServiceWiringException($key),
        };
    }

    public function wire(Wirings $wirings): Runnable|null
    {

        $urlFactory = $wirings->get("kernel")->server("url-factory");
        $filter = new WebPackageFilter($rulesBagFactory->createRulesBag(new ReflectionClass(WebPackageRules::class)));
        $this->store = new WebPackageStore(
            webPackageFilter: $filter,
            pemFilter:        new PemFilter,
            webPackageUrl:    $urlFactory->createUrl($this->serviceDirectory . $this->webpackage),
            serverKeyUrl:     $urlFactory->createUrl($this->serviceDirectory . $this->serverKey),
            clientKeyUrl:     $urlFactory->createUrl($this->serviceDirectory . $this->clientKey),
        );
        $router = $wirings->get("http")->server("router");
        $router->mount("/", new WebPackageController());

        return null;
    }

    public function service(string $key): object
    {
        return match ($key) {
            "store" => $this->store,
            default => throw new NoSuchServiceWiringException($key),
        };
    }

    private function setServiceDirectory(string $value): string
    {
        if (str_starts_with($value, "file:") === false) {
            if (str_starts_with($value, "/") === false) {
                throw new WiringException("Relative paths are not supported for service-directory: $value");
            }
            $value = "file:" . $value;
        }
        if (str_ends_with($value, "/") === false) {
            $value .= "/";
        }
        return $this->serviceDirectory = $value;
    }
}
