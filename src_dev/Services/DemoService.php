<?php

namespace App\Services ;

use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use Psr\Log\LoggerInterface;

class DemoService
{
    /**
     * Exemple type de tâche lancée par le gestionnaire de tâche :
     * apidae:tache:run X
     *
     * @return bool
     */
    public static function demo(Tache $tache, LoggerInterface $logger): bool
    {
        $steps = 5 ;
        $logger_context = ['id' => $tache->getId(), 'steps' => $steps] ;
        $step = 1 ;
        do {
            $logger_context['step'] = $step ;
            $logger->info('Début de l\'étape...', $logger_context) ;
            // Do whatever this task has to do
            sleep(2) ;
            $step++ ;
            $logger->info('Etape terminée !', $logger_context) ;
        } while ($step <= $steps) ;
        return true ;
    }
}
