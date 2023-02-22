<?php

namespace ApidaeTourisme\ApidaeBundle\Config;

use Symfony\Component\Console\Command\Command;

/**
 * @see https://symfony.com/doc/current/reference/forms/types/enum.html
 */

enum TachesStatus: string
{
    case TO_RUN = 'TO_RUN' ;
    case RUNNING = 'RUNNING' ;
    case COMPLETED = 'COMPLETED' ;
    case FAILED = 'FAILED' ;
    case INTERRUPTED = 'INTERRUPTED' ;
    case CANCELLED = 'CANCELLED' ;
}
