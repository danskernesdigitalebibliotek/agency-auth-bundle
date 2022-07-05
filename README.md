# Agency Auth Bundle

[![Github](https://img.shields.io/badge/source-danskernesdigitalebibliotek/agency--auth--bundle-blue?style=flat-square)](https://github.com/danskernesdigitalebibliotek/agency-auth-bundle)
[![Release](https://img.shields.io/packagist/v/danskernesdigitalebibliotek/agency-auth-bundle.svg?style=flat-square&label=release)](https://packagist.org/packages/danskernesdigitalebibliotek/agency-auth-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/danskernesdigitalebibliotek/agency-auth-bundle.svg?style=flat-square&colorB=%238892BF)](https://www.php.net/downloads)
[![Build Status](https://img.shields.io/github/workflow/status/danskernesdigitalebibliotek/agency-auth-bundle/Test%20%26%20Code%20Style%20Review?label=CI&logo=github&style=flat-square)](https://github.com/danskernesdigitalebibliotek/agency-auth-bundle/actions?query=workflow%3A%22Test+%26+Code+Style+Review%22)
[![Codecov Code Coverage](https://img.shields.io/codecov/c/gh/danskernesdigitalebibliotek/agency-auth-bundle?label=codecov&logo=codecov&style=flat-square)](https://codecov.io/gh/danskernesdigitalebibliotek/agency-auth-bundle)
[![Read License](https://img.shields.io/packagist/l/danskernesdigitalebibliotek/agency-auth-bundle.svg?style=flat-square&colorB=darkcyan)](https://github.com/danskernesdigitalebibliotek/agency-auth-bundle/blob/master/LICENSE.txt)
[![Package downloads on Packagist](https://img.shields.io/packagist/dt/danskernesdigitalebibliotek/agency-auth-bundle.svg?style=flat-square&colorB=darkmagenta)](https://packagist.org/packages/danskernesdigitalebibliotek/agency-auth-bundle/stats)

This bundle enables _agency_ ("library") authentication against the [Open Platform](https://openplatform.dbc.dk/v3/) (Shared API for danish public libraries). In order to use this bundle you must have a `CLIENT_ID / CLIENT_SECRET` pair from DBC.

The bundle validates _agency_ access tokens against the Open Platform introspection endpoint. If a supplied token is valid a `User` object with `ROLE_OPENPLATFORM_AGENCY` will be available from Symfony's [security component](https://symfony.com/doc/4.4/security.html#b-fetching-the-user-from-a-service).  

## Note

If you need _user_ ("personal") authentication you should use [danskernesdigitalebibliotek/oauth2-adgangsplatformen](https://github.com/danskernesdigitalebibliotek/oauth2-adgangsplatformen)

## Installation

Use Composer to install the bundle: `composer require danskernesdigitalebibliotek/agency-auth-bundle`

## Bundle Configuration

Add a `config/packages/ddb_agency_auth.yaml` file:

```dotenv
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

```dotenv
###> Openplatform ###
OPENPLATFORM_ID=myId
OPENPLATFORM_SECRET=mySecret
OPENPLATFORM_INTROSPECTION_URL=https://login.bib.dk/oauth/introspection
OPENPLATFORM_ALLOWED_CLIENTS=''
###< Openplatform ###
```

Then set the actuel values in your `.env.local`. (See [configuration based on environment variables](https://symfony.com/doc/current/configuration.html#configuration-based-on-environment-variables))

## Security Configuration

Configure firewalls, access control and roles according to your needs in your `config/packages/security.yml`. The bundle provides a `TokenAuthenticator` you can use as a [custom authenticator](https://symfony.com/doc/current/security/custom_authenticator.html) and a `OpenPlatformUserProvider` you can use as a [custom user provider](https://symfony.com/doc/current/security/user_providers.html#creating-a-custom-user-provider).
If authenticated it will return a [self validating passport](https://symfony.com/doc/current/security/custom_authenticator.html#self-validating-passport) with a `User` with the `ROLE_OPENPLATFORM_AGENCY`. You can use Symfonys [hierarchical roles](https://symfony.com/doc/4.4/security.html#hierarchical-roles)
to map this role to your applications roles.

A working security configuration could be:

```yaml
security:
    # https://symfony.com/doc/current/security.html#where-do-users-come-from-user-providers
    providers:
        openplatform_provider:
            id: DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\OpenPlatformUserProvider
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        main:
            stateless: true
            custom_authenticators:
                - DanskernesDigitaleBibliotek\AgencyAuthBundle\Security\TokenAuthenticator

    access_control:
        # Allows accessing the Swagger UI
        - { path: '^/api/docs', roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: '^/api', roles: ROLE_API_USER }

    role_hierarchy:
        ROLE_OPENPLATFORM_AGENCY: [ROLE_API_USER, ROLE_ENTRY_READ]
```

## Development Setup

A `docker-compose.yml` file with a PHP 7.4 image is included in this project.
To install the dependencies you can run

```shell
docker compose up -d
docker compose exec phpfpm composer install
```

### Unit Testing

A PhpUnit setup is included in this library. To run the unit tests:

```shell
docker compose exec phpfpm composer install
docker compose exec phpfpm ./vendor/bin/simple-phpunit
```

### Psalm static analysis

We are using [Psalm](https://psalm.dev/) for static analysis. To run
psalm do

```shell
docker compose exec phpfpm composer install
docker compose exec phpfpm ./vendor/bin/psalm
```

### Check Coding Standard

The following command let you test that the code follows
the coding standard for the project.

* PHP files (PHP-CS-Fixer)

    ```shell
    docker compose exec phpfpm composer check-coding-standards
    ```

* Markdown files (markdownlint standard rules)

    ```shell
    docker run -v ${PWD}:/app itkdev/yarn:latest install
    docker run -v ${PWD}:/app itkdev/yarn:latest check-coding-standards
    ```

### Apply Coding Standards

To attempt to automatically fix coding style

* PHP files (PHP-CS-Fixer)

    ```sh
    docker compose exec phpfpm composer apply-coding-standards
    ```

* Markdown files (markdownlint standard rules)

    ```shell
    docker run -v ${PWD}:/app itkdev/yarn:14 install
    docker run -v ${PWD}:/app itkdev/yarn:14 apply-coding-standards
    ```

## CI

Github Actions are used to run the test suite and code style checks on all PR's.

If you wish to test against the jobs locally you can install [act](https://github.com/nektos/act).
Then do:

```sh
act -P ubuntu-latest=shivammathur/node:latest pull_request
```

## Versioning

We use [SemVer](http://semver.org/) for versioning.
For the versions available, see the
[tags on this repository](https://github.com/itk-dev/openid-connect/tags).

## License

This project is licensed under the AGPL-3.0 License - see the
[LICENSE.md](LICENSE.md) file for details
