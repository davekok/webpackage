<?php

declare(strict_types=1);

namespace davekok\webpackage;

class WebPackageContainer
{
    public function __construct(
        public readonly WebPackageFilter $filter,
        public readonly WebPackageStore $store,
    ) {}
}
