<?php

namespace App\Services ;

use Psr\Log\LoggerInterface;
use ApidaeTourisme\ApidaeBundle\Entity\Tache;

class DemoService
{
    /**
     * Exemple type de tâche lancée par le gestionnaire de tâche :
     * apidae:tache:run X
     */
    public static function demo(Tache $tache, LoggerInterface $logger): bool
    {
        $steps = 5 ;
        $logger_context = ['id' => $tache->getId(), 'steps' => $steps] ;
        $step = 1 ;
        do {
            $logger_context['step'] = $step ;
            $tache->log('info', 'Début de l\'étape '.$step.'...');
            $logger->info('Début de l\'étape...', $logger_context) ;
            // Do whatever this task has to do
            sleep(2) ;
            $step++ ;
            $tache->log('info', 'Fin de l\'étape '.$step.'...');
            $logger->info('Etape terminée !', $logger_context) ;
        } while ($step <= $steps) ;

        return true ;
    }
}
