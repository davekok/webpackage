<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\kernel\Activity;
use davekok\kernel\FileUrl;
use davekok\kernel\OpenMode;
use Psr\Logger\LoggerInterface;

class WebPackageStore
{
    public function __construct(
        public readonly WebPackageFilter $webPackageFilter,
        public readonly PemFilter $pemFilter,
        public readonly FileUrl $webPackageUrl,
        public readonly FileUrl $serverKeyUrl,
        public readonly FileUrl $clientKeyUrl,
        public WebPackage|null $webPackage = null,
        public WebPackageServerKey|null $serverKey = null,
        public WebPackageClientKey|null $clientKey = null,
    ) {}

    public function loadWebPackage(Activity $activity): void
    {
        if ($this->webPackageUrl->isFile($activity) === false) {
            return;
        }

        $this->webPackageFilter->read(
            actionable: $this->webPackageUrl->open($activity, OpenMode::READ_ONLY),
            setter:     fn(WebPackage $webPackage) => $this->webPackage = $webPackage,
        );
    }

    public function saveWebPackage(Activity $activity): void
    {
        $this->webPackageFilter->write(
            actionable: $this->webPackageUrl->open($activity, OpenMode::TRUNCATE_WRITE_ONLY),
            webPackage: $this->webPackage,
        );
    }

    public function loadServerKey(Activity $activity): void
    {
        if ($this->serverKeyUrl === null || $this->serverKeyUrl->isFile($activity) === false) {
            return;
        }

        $this->pemFilter->read(
            actionable: $this->serverKeyUrl->open($activity, OpenMode::READ_ONLY),
            setter:     fn(OpenSSLAsymmetricKey $serverKey) => $this->serverKey = $serverKey,
        );
    }

    public function saveServerKey(Activity $activity): void
    {
        $this->pemFilter->write(
            actionable: $this->serverKeyUrl->open($activity, OpenMode::TRUNCATE_WRITE_ONLY),
            key:        $this->serverKey,
        );
    }

    public function createServerKey(Activity $activity, LoggerInterface $logger): void
    {
        $key = openssl_pkey_new([
            "digest_alg"       => "sha3-512",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        ]) ?: throw new ContainerException("Failed to create private key.");

        $this->serverKey = new WebPackageServerKey($key);

        $this->saveServerKey($activity);

        $logger->notice(
            "WebPackage Client Key:\n"
            . (openssl_pkey_get_details($key) ?: throw new ContainerException("Failed to get details."))['key']
        );
    }

    public function loadClientKey(Activity $activity): void
    {
        if ($this->clientKeyUrl === null || $this->clientKeyUrl->isFile($activity) === false) {
            return;
        }

        $this->pemFilter->read(
            actionable: $this->clientKeyUrl->open($activity, OpenMode::READ_ONLY),
            setter:     fn(OpenSSLAsymmetricKey $clientKey) => $this->clientKey = $clientKey,
        );
    }

    public function saveClientKey(Activity $activity): void
    {
        $this->pemFilter->write(
            actionable: $this->clientKeyUrl->open($activity, OpenMode::TRUNCATE_WRITE_ONLY),
            key:        $this->clientKey,
        );
    }
}
