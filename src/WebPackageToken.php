<?php

declare(strict_types=1);

namespace davekok\webpackage;

enum WebPackageToken: int
{
    case SIGNATURE        = 0x89;
    case END_OF_FILES     = 0x00;
    case BUILD_DATE       = 0x01;
    case CONTENT_ENCODING = 0x02;
    case FILE_NAME        = 0x10;
    case CONTENT_TYPE     = 0x11;
    case CONTENT_LENGTH   = 0x12;
    case START_CONTENT    = 0xFF;
}
