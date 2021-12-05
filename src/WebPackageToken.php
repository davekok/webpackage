<?php

declare(strict_types=1);

namespace davekok\webpackage;

enum WebPackageToken: int
{
    case SIGNATURE        = 0x89;
    case DOMAIN           = 0x01;
    case BUILD_DATE       = 0x02;
    case CERTIFICATE      = 0x03;
    case CONTENT_ENCODING = 0x04;
    case FILE_NAME        = 0x10;
    case CONTENT_TYPE     = 0x11;
    case CONTENT_HASH     = 0x12;
    case CONTENT_LENGTH   = 0x13;
    case START_CONTENT    = 0xFF;
    case END_OF_FILES     = 0x00;
}
