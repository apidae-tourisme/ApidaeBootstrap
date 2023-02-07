# Réutilisation du SSO sur un projet utilisant ApidaeBootstrap comme dépendance

Dans un projet symfony, après avoir fait `composer require apidae-tourisme/apidae-bootstrap`

## `config.routes.yaml`
```yml
apidae_login:
    path: /login/check-apidae
```

## `config/routes/hwi_oauth_routing.yaml`
Copier le même contenu que le fichier `vendor/apidae-tourisme/apidae-bootstrap/config/routes/hwi_oauth_routing.yaml`

## `config/packages/hwi_oauth.yaml`
```yml
    resource_owners:
        apidae:
            type: oauth2
            class: \App\Security\ApidaeResourceOwner
            client_id: "%env(APIDAE_SSO_ID)%"
            client_secret: "%env(APIDAE_SSO_SECRET)%"
            access_token_url: "%env(APIDAE_SSO_URLACCESSTOKEN)%"
            authorization_url: "%env(APIDAE_SSO_URLAUTHORIZE)%"
            infos_url: "%env(APIDAE_SSO_URLRESOURCEOWNERDETAILS)%"
            scope: "sso"
            user_response_class: HWI\Bundle\OAuthBundle\OAuth\Response\PathUserResponse
            paths:
                identifier: id
                nickname: email
                realname: fullname
```

## `config/packages/security.yaml`

## `.env.(cooking|dev|prod)`
APIDAE_SSO_REDIRECTURI='https://base.apidae-tourisme.(cooking|dev|prod)/oauth/authorize'
APIDAE_SSO_URLAUTHORIZE='https://base.apidae-tourisme.(cooking|dev|prod)/oauth/authorize'
APIDAE_SSO_URLACCESSTOKEN='https://api.apidae-tourisme.(cooking|dev|prod)/oauth/token'
APIDAE_SSO_URLRESOURCEOWNERDETAILS='https://api.apidae-tourisme.(cooking|dev|prod)/api/v002/sso/utilisateur/profil'

## `.env.local` ou `.env.(cooking|dev|prod).local`
APIDAE_SSO_ID=
APIDAE_SSO_SECRET=
APIDAE_SSO_ENV=cooking|dev|prod
APIDAE_MEMBRES_PROJETID=
APIDAE_MEMBRES_APIKEY=
APIDAE_MEMBRES_ENV=cooking|dev|prod

## `config/services.yaml`
```yml
services:
    [...]
    PierreGranger\ApidaeMembres:
        class: PierreGranger\ApidaeMembres
        arguments:
            - projet_consultation_projetId: "%env(int:APIDAE_MEMBRES_PROJETID)%"
              projet_consultation_apiKey: "%env(APIDAE_MEMBRES_APIKEY)%"
              debug: "%env(bool:APIDAE_MEMBRES_DEBUG)%"
              env: "%env(APIDAE_MEMBRES_ENV)%"
```