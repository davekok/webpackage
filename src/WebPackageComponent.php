<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\kernel\Actionable;
use davekok\kernel\Activity;
use davekok\kernel\OpenMode;
use davekok\lalr1\Parser;
use davekok\lalr1\RulesBag;
use davekok\lalr1\RulesBagFactory;
use ReflectionClass;

class WebPackageComponent
{
    public readonly RulesBag $rulesBag;

    public function __construct(RulesBagFactory $rulesBagFactory = new RulesBagFactory())
    {
        $this->rulesBag = $rulesBagFactory->createRulesBag(new ReflectionClass(WebPackageRules::class));
    }

    public function read(Actionable $actionable, callable $andThen): void
    {
        $actionable instanceof Readable ?: throw new InvalidArgumentException("Expected an readable actionable.");
        $actionable->read($this->createReader(), $andThen);
    }

    public function write(Actionable $actionable, WebPackage $webPackage): void
    {
        $actionable instanceof Writable ?: throw new InvalidArgumentException("Expected an writable actionable.");
        $actionable->write($this->createWriter($webPackage));
    }

    public function createReader(): WebPackageReader
    {
        return new WebPackageReader(new Parser($this->rulesBag, new WebPackageRules));
    }

    public function createWriter(WebPackage $webPackage): WebPackageWriter
    {
        return new WebPackageWriter($webPackage);
    }

    public function createServerKey(Activity $activity, string $path): WebPackageServerKey
    {
        if (file_exists($path)) {
            return new WebPackageServerKey(
                openssl_pkey_get_private(file_get_contents($path) ?: throw new Exception("Unable to read: {$path}"))
                ?: throw new Exception("Unable to read: {$path}")
            );
        }

        $key = openssl_pkey_new([
            "digest_alg"       => "sha3-512",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        ]) ?: throw new Exception("Failed to create private key.");

        openssl_pkey_export_to_file($key, $path) ?: throw new Exception("Failed to export key: $path");
        chmod($path, 0o600) ?: throw new Exception("Unable to set permissions: {$path}");

        $activity->logger->notice(
            "WebPackage Client Key:\n"
            . (openssl_pkey_get_details($key) ?: throw new Exception("Failed to get details."))['key']
        );

        return new WebPackageServerKey($key);
    }

    public function createClientKey(string $path): WebPackageClientKey
    {
        return new WebPackageClientKey(
            openssl_pkey_get_public(
                file_get_contents(
                    realpath($path) ?: throw new Exception("File {$path} is missing.")
                ) ?: throw new Exception("Could not read {$path}.")
            ) ?: throw new Exception("Unable to parse {$path}.")
        );
    }
}
