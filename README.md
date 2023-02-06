# ApidaeBootstrap

## Utilisation avec Symfony

Ce projet a été monté initialement pour fournir des dépendances communes à des projets Symfony.
Les dépendances sont :
- Des fichiers scss
- Des fichiers twig
- Des classes PHP pour le SSO

Ces éléments peuvent être réutilisés hors projet symfony (en particulier la partie scss).

Pour utiliser ce projet avec Symfont : ne CLONEZ PAS ce dépôt.
Ce dépôt est un dépôt de travail, qui met à disposition les fichiers cités ci-dessus par npm (scss) et composer (twig & sso).

```bash
composer require apidae-tourisme/apidae-bootstrap
```
```bash
npm install apidae-tourisme/apidae-bootstrap
```

## Utilisation des fichiers scss avec Webpack Encore sur un projet Symfony

Ajouter :
```css
@import '~apidae-bootstrap/scss/apidae.scss';
```

dans votre fichier `styles/app.scss`

## Utilisation des fichiers twig

Dans votre projet Symfony utilisant twig, renseignez les valeurs suivants dans `config/packages.twig.yaml` :
```yml
twig:
    [...]
    paths:
        "%kernel.project_dir%/vendor/apidae-tourisme/apidae-bootstrap/templates": ""
    [...]
    globals:
        app_env: "%env(APP_ENV)%"
        app_title: "%env(APP_TITLE)%"
```

Les lignes importants sont le paths additionnel, qui permet d'utiliser les templates ici-présents, et les 2 variables globales :
- `app_env` : passe le thème aux couleurs dev/cooking
- `app_title` : ex : Console

Avec cette configuration, vous pouvez appeler n'importe quel template twig présent dans ce dépôt (ex: `base.html.twig`)
Si un fichier ne vous convient pas, vous pouvez le créer avec le même path dans votre projet (ex: `MonProjetSymfony/templates/header.user.html.twig`) : dans ce cas, votre fichier prendra la priorité sur le fichier de ce dépôt (`MonProjetSymfony/vendor/apidae-tourisme/apidae-bootstrap/templates/header.user.html.twig`)

## Utilisation des classes PHP pour le SSO