<?php

namespace STS\Keep\Enums;

enum MissingSecretStrategy: string
{
    case FAIL = 'fail';
    case REMOVE = 'remove';
    case BLANK = 'blank';
    case SKIP = 'skip';
}