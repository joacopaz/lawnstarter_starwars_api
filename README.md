# Laravel multi-container full stack application

## Requirements

- [Docker](https://docs.docker.com/desktop/) installed *and daemon running*
- [NPM](https://docs.npmjs.com/downloading-and-installing-node-js-and-npm)
- [PHP](https://www.php.net/downloads.php)
- [Composer](https://getcomposer.org/)
- [sed](https://www.gnu.org/software/sed/) (preinstalled in most UNIX systems)

> All of the above must be present in the CLI/path since composer
will try to run them with their commands

- Set up a `.env` file (see `.env.example` for reference, the actual `.env` should
have been provided privately), make sure the file exists and is populated.

- Set up a `.env.testing` (also should be provided).

## ⚠️ PostgreSQL PHP drivers  ⚠️

> This step is only needed if you want to locally run dev or tests, not for composer
build

If you're missing the pgsql drivers, you will see an issue when running `composer
dev` or `composer test` (not `composer build`, which runs the container DB command).
The issue will look like a DB command failed (this is in the migration step).
The easiest way to install them is to [install herd](https://herd.laravel.com).

By default the laravel page install guide installs `herd-lite`, which does not include
those drivers. It's as simple as going to the above link, choose your system and
install it. Then restart your terminal and you should be good to go!

Herd generally places its env path variable on top of other PHP installs. So generally
you wont need any manual assignment of the variable over the pre-existing PHP one.
You can uninstall it later once you're done with this app.

You can see what php your system is using by running `php --ini`, if the ini is
inside of the herd directory, then you're on the right path!

If the system is in the `herd-lite` directory this is not ok and need herd full install.

If you're using the oficial PHP full install, you can enable these extensions in
the `php.ini` by uncommenting `pgsql` and `pdo_pgsql` from the ini file.

> - For windows the Windows Subsystem for Linux (WSL) is recommended,
since it has pgsql drivers pre-installed and will work with any install off the box.

## Instructions

1. Run `composer setup` in the root of this folder to fetch dependencies.

2. You can run tests to verify integrity with `composer test`. If your system
is not computing the local pgsql database due to driver issues, please skip
this test and run the build. You can still perform any task directly in the
container to avoid any local environment constraints.

3.
    - Run `composer build` for running it containerized @ localhost:80

    > Disregard the comment `pull access denied for app, repository
    does not exist or may require 'docker login'`, this is expected since the app
    is a local artifact, the image is not pulled from an artifacts repo

    - Run `composer dev` for local development @ localhost:8000

> `dev` and `build` will start the application container (especially for the DB),
 once done remember to stop them to avoid unnecessary background processes

## Issues

- Make sure all the requirements are met, you can check in the CLI with:

```bash
    docker --version
    npm --version
    composer --version # should output both Composer and PHP version
    sed --version
    test -f .env && echo ".env exists" || (echo ".env is missing")
```

- If docker is failing make sure the docker daemon is started (run `docker info`
on the terminal and check its output or open docker desktop and see the bottom
left if `Engine is running`)

- If `sed` is installed but missbehaving check that your .env `DB_HOST`
key matches .env.example

> Alternatively you can just remove the sed step from `composer.json`
and just change your .env DB_HOST manually based on the comments in the file

- If all else fails feel free to reach out to [joacopaz@gmail.com](mailto:joacopaz@gmail.com)

### Utils

- If you wish to access the PostgreSQL DB in the container
run `psql -U lawnstarter_admin -d star_wars`

- For running the statistic computations programmatically run:

```php
    php artisan tinker
    App\Jobs\ComputeQueryStatistics::dispatch()
```

- For viewing stats visit: `/api/v1/stats`,
preprend localhost:8000 for dev or localhost:80 for container.
Can be viewed in cURL, browser or any networking tool.

- For tests run `composer test`

- If the local env is not working, you can still run them in the
container, just run `php artisan migrate --env=testing --force` and `php artisan
test` inside the container

### Disclaimer

- No AI wrote this code, it's only use was for research purposes (sort of like an
advanced google), most scaffolding is boilerplate coming from `laravel new`
command and looking at the `sail` setup and adapting it for the PostgreSQL setup,
I tried to remove most of the unneeded boilerplate but some might've sneaked in there
