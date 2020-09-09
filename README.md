# Agency Auth Bundle

[![Github](https://img.shields.io/badge/source-danskernesdigitalebibliotek/agency--auth--bundle-blue?style=flat-square)](https://github.com/danskernesdigitalebibliotek/agency-auth-bundle)
[![Release](https://img.shields.io/packagist/v/danskernesdigitalebibliotek/agency-auth-bundle.svg?style=flat-square&label=release)](https://packagist.org/packages/danskernesdigitalebibliotek/agency-auth-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/danskernesdigitalebibliotek/agency-auth-bundle.svg?style=flat-square&colorB=%238892BF)](https://www.php.net/downloads)
[![Build Status](https://img.shields.io/github/workflow/status/danskernesdigitalebibliotek/agency-auth-bundle/Test%20%26%20Code%20Style%20Review?label=CI&logo=github&style=flat-square)](https://github.com/danskernesdigitalebibliotek/agency-auth-bundle/actions?query=workflow%3A%22Test+%26+Code+Style+Review%22)
[![Codecov Code Coverage](https://img.shields.io/codecov/c/gh/danskernesdigitalebibliotek/agency-auth-bundle?label=codecov&logo=codecov&style=flat-square)](https://codecov.io/gh/danskernesdigitalebibliotek/agency-auth-bundle)
[![Read License](https://img.shields.io/packagist/l/danskernesdigitalebibliotek/agency-auth-bundle.svg?style=flat-square&colorB=darkcyan)](https://github.com/danskernesdigitalebibliotek/agency-auth-bundle/blob/master/LICENSE.txt)
[![Package downloads on Packagist](https://img.shields.io/packagist/dt/danskernesdigitalebibliotek/agency-auth-bundle.svg?style=flat-square&colorB=darkmagenta)](https://packagist.org/packages/danskernesdigitalebibliotek/agency-auth-bundle/stats)


This bundle enables _agency_ ("library") authentication against the [Open Platform](https://openplatform.dbc.dk/v3/) (Shared API for
danish public libraries). In order to use this bundle you must have a `CLIENT_ID / CLIENT_SECRET` pair from DBC.

The bundle validates _agency_ access tokens against the Open Platform introspection endpoint. If a supplied token is 
valid a `User` object with `ROLE_OPENPLATFORM_AGENCY` will be available from Symfony's [security component](https://symfony.com/doc/4.4/security.html#b-fetching-the-user-from-a-service).  

### Note:
If you need _user_ ("personal") authentication you should use [danskernesdigitalebibliotek/oauth2-adgangsplatformen](https://github.com/danskernesdigitalebibliotek/oauth2-adgangsplatformen) 

## Installation

Use Composer to install the bundle: `composer require danskernesdigitalebibliotek/agency-auth-bundle`

## Bundle Configuration

Add a `config/packages/ddb_agency_auth.yaml` file:

```
ddb_agency_auth:
    # Your client id supplied by DBC
    openplatform_id: '%env(OPENPLATFORM_ID)%'
    
    # Your client secret supplied by DBC
    openplatform_secret: '%env(OPENPLATFORM_SECRET)%'
    
    # The introspection URL to query against
    openplatform_introspection_url: 'https://login.bib.dk/oauth/introspection'
    
    # A comma separated allow list of CLIENT_IDs. An empty list allows all.
    openplatform_allowed_clients: '%env(OPENPLATFORM_ALLOWED_CLIENTS)%'

    # [Optional] A service id for the cache service to use for caching token/user pairs 
    auth_token_cache: token.cache

    # [Optional] A service id for the logger to use for error logging.
    auth_logger: logger
```

In your `.env` add:

```
###> Openplatform ###
OPENPLATFORM_ID=myId
OPENPLATFORM_SECRET=mySecret
OPENPLATFORM_INTROSPECTION_URL=https://login.bib.dk/oauth/introspection
OPENPLATFORM_ALLOWED_CLIENTS=''
###< Openplatform ###
```

Then set the actuel values in your `.env.local`. (See [configuration based on environment variables](https://symfony.com/doc/current/configuration.html#configuration-based-on-environment-variables)) 


## Security Configuration

Configure firewalls, access control and roles according to your needs in your `config/packages/security.yml`. 
The bundle provides a `TokenAuthenticator` you can use as a [Symfony Guard](https://symfony.com/doc/4.4/security/guard_authentication.html).
If authenticated it will return a `User` with the `ROLE_OPENPLATFORM_AGENCY`. You can use Symfonys [hierarchical roles](https://symfony.com/doc/4.4/security.html#hierarchical-roles)
to map this role to your applications roles.

A working security configuration could be:
```
security:
    providers:
        in_memory: { memory: null }

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        api:
            pattern: ^/api
            stateless: true
            anonymous: lazy
            guard:
                authenticators:
                    - DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\TokenAuthenticator
        main:
            anonymous: true

    access_control:
        # Allows accessing the Swagger UI
        - { path: '^/api/docs', roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: '^/api', roles: ROLE_OPENPLATFORM_AGENCY }

    role_hierarchy:
        ROLE_OPENPLATFORM_AGENCY: [ROLE_API_USER, ROLE_ENTRY_READ]
```