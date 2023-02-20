# Créer une tâche
Le plus souvent à partir d'un controler (après clic sur un bouton, envoi d'un formulaire...) :

```php
use ApidaeTourisme\ApidaeBundle\Services\TachesServices ; // $tachesServices (service)

$tache = new Tache();
$tache->setMethod('App\\Services\\TIFService:extractAll');
$tache->setParametres(['source' => $source]);
$tachesServices->add($tache) ;
```

# Lancer le gestionnaire de tâche
```bash
bin/console apidae:tachesManager:start
```

# Lancer une tâche seule (ex: id 18)
C'est exactement ce que ferait le gestionnaire de tâche à partir des tâches TO_RUN : il relance une commande
```bash
bin/console apidae:tache:run 18
```

# TODO : refaire la partie ci-dessous

- `string` **tache** : Nom de la classe et de la fonction qui seront lancées sous cette forme :
    - `$this->{lcfirst("DescriptifsThematises")}->{"import"}($tache->getId())`
    - `$this->descriptifsThematises->import($tache->getId())`
    - Voir `App\Service\TachesExecuter::exec` pour plus de détails
- `array` **parametres** : paramètres nécessaires pour faire votre tâche (propre à chaque type de tâche)
- `string` **fichier** (optionnel) : S'il y a un fichier (import...), doit être un `Symfony\Component\HttpFoundation\File\UploadedFile` (provient d'un formulaire). Le paramètre est optionnel. Il peut aussi être ajouté plus tard.
- `array` **parametresCaches** : Paramètres qui ne doivent pas être exposés : token d'écriture par exemple

# Exécuter une tâche
```php
use App\Service\Taches ; // $taches (service)
$taches->start($id);
```
```shell
bin/console app:taches:run id
```

# Ecrire une nouvelle tâche
Voir l'exemple de `Service\Traitements\SITUtilisateurs` qui est simple et documenté pour comprendre le fonctionnement.

La tâche doit étendre `Service\Traitements\Manager`
La tâche doit renvoyer true si elle se termine (avec ou sans erreur), false si elle ne se termine pas (erreur grave en cours de traitement).

Tout au long de son cycle de vie, on peut envoyer des logs par `Manager::log()` (donc `$this->log(...)`) qui seront envoyés dans le logger de tâches, et dans les résultats de la tâche pour affichage dans l'IHM.

La tâche doit idéalement traiter elle-même toutes les exceptions qu'elle peut rencontrer : un fallback est prévu dans le cas contraire, mais le fallback sera moins précis qu'un traitement direct dans la tâche où on va pouvoir préciser la nature de l'erreur : l'erreur doit être loguée `$this->log('error','Message',['details']?)` puis la tâche doit retourner `false` pour indiquer qu'elle n'est pas arrivée à son terme à cause d'une erreur majeure.

Un `$this->log('error',...)` ne doit pas forcément être suivi d'un `return false` : tout dépend si l'erreur empêche la tâche de continuer ou pas.

## Debug
Lorsqu'on lance la tâche en ligne de commande, on peut rajouter un paramètre "1" derrière l'id X de tâche :
`bin/console app:tache:run X 1`

Ce paramètre permet d'activer l'affichage en live des logs dans la console, et d'avoir des infos supplémentaires, envoyées par Manager::debug() (ces infos ne sont pas loguées, elles ne sont présentes qu'en mode debug et seulement en affichage live)

## Progression
Durant le cycle de vie, la tâche peut également utiliser un système de progression, voir `Service\Traitements\Progress`. La progression sera enregistrée en base et affichée en IHM.