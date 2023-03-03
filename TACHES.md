# Créer une tâche
Le plus souvent à partir d'un controler (après clic sur un bouton, envoi d'un formulaire...) :

```php
use ApidaeTourisme\ApidaeBundle\Services\TachesServices ;

$tache = new Tache();
$tache->setMethod('App\\Services\\TIFService:extractAll');
$tache->setParametres(['source' => $source]);
$tachesServices->add($tache) ;
```

Méthode statique :
```
App\\Services\\TIFService::extractAll
```
Méthode non statique :
```
App\\Services\\TIFService:extractAll
```

Globalement on préferera utiliser des méthodes non statiques parce qu'elles permettent de récupérer les services par autowire.

# Lancer le gestionnaire de tâche
```bash
bin/console apidae:tachesManager:start
```

Il doit théoriquement tourner en permanence sur le serveur.

En général on positionne une tâche cron qui le lance toutes les minutes :
Chaque lancement de la commande déclenche `APIDAEBUNDLE_TACHES_LOOP=10` tours avec un sleep de `APIDAEBUNDLE_TACHES_SLEEP=6` secondes (donc pourra lancer au maximum 10 tâches par secondes).

Ces variable sont ajustables par `.env`.

En local, on peut le lancer une seule fois en le configurant pour faire des cycles plus longs :
```bash
# .env.local
APIDAEBUNDLE_TACHES_LOOP=10000
```

# Lancer une tâche seule (ex: id 18)
Le gestionnaire de tâches n'exécute pas les tâches lui même (on ne maitriserait pas son temps d'exécution ni les risques de plantage) :

Il se contente de lancer les tâches `TO_RUN` de façon unitaire :
```bash
bin/console apidae:tache:run 18 -vv
```

On peut donc aussi utiliser cette commande en bash pour lancer une tâche à la main.

Le `-vv` rend la commande plus verbeuse, ce qu'on souhaite en général faire quand on la lance à la main.

# Ajouter une tâche par IHM
L'ajout d'une tâche peut se faire suite à une action IHM, y compris en ajax, mais doit toujours être contrôlé côté serveur.

Il n'y a pas de méthode automatique proposée par ce bundle pour créer une nouvelle tâche par IHM : c'est à l'application de s'auto-gérer pour la création de la tâche (ajax ou non).

Le statut `TO_RUN` est affecté automatiquement et la tâche devra être lancée par le gestionnaire de tâche.

```php
$tache = new Tache() ;
$tache->setMethod('App\\Services\\DemoService:demo2') ;
$tache->setParametres($parametres) ;
$tache->setSignature('action1_sur_objetA') ;
$tache_id = $tachesServices->add($tache);
```

# Monitoring IHM

## Monitoring IHM côté serveur (au chargement de la page)
La plupart du temps on va chercher à afficher les infos d'une tâche sur l'IHM à partir d'infos récupérées côté serveur (donc dans le controller).

### Afficher le statut d'une tâche
#### Utiliser le template twig en lui passant une tâche
Suppose d'avoir récupéré la tâche côté controller et de l'avoir passé à twig :
```php
// src/Controller/MaPageController.php
$taches = $this->tacheRepository->findBy([...]);
$this->render('mapage.html.twig',['taches' => $taches]) ;
```
```twig
{# templates/mapage.html.twig #}
{% for tache in taches %}
    {% include 'taches/status.html.twig' with {tache:tache} %}
{% endfor %}
```
#### Demander à twig de générer le rendu d'une tâche à partir de son id
C'est plus simple, mais plus lourd aussi puisqu'ici, on va demander à twig d'appeler la fonction `ApidaeBundle\Controller\TacheController::status` (path `apidaebundle_taches_status`), qui va donc aller rechercher la tâche en bdd de façon unitaire.
C'est intéressant pour une poignée de tâches mais pas sur un listing donc on ne maîtrise pas le volume.
```twig
{{ render(path('apidaebundle_taches_status', {id:33})) }}
```

## Monitoring JS des tâches par IHM
Lors du chargement d'une page, et tout au long de son affichage, le bundle tentera de monitorer les tâches :

### Par signature
```html
<span class="tache" data-signature="tache_truc_b"></span>
```
Lorsque ce bloc html est rencontré dans la page, le monitoring JS tentera de trouver la dernière tâche possédant cette signature, et d'en afficher les informations.
Seule la dernière tâche avec cette signature sera affichée.

### Par identifiant de tâche
```html
<span class="tache" data-signature="tache_truc_b"></span>
```

### Both
```html
<span class="tache" data-?="" data-status=["TO_RUN","FAILED","RUNNING"]'>
```
L'information data-status permet de n'afficher les infos de la tâche concernée (dernière tâche avec la signature ou par identifiant) que si elle est à l'un de ces états.
Sinon, rien ne sera affiché.

---
---
---
---

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