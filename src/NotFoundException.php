<?php

declare(strict_types=1);

namespace davekok\webpackage;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends WebPackageException implements NotFoundExceptionInterface {}
