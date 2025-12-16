# Lawnstarter Containarized Star Wars Api

Welcome to this sample project for my interview,
and thanks for taking the time to check it out!

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

## Instructions

1. Run `composer setup` in the root of this folder to fetch dependencies.

2.
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

### Disclaimer

- No AI wrote this code, it's only use was for research purposes (sort of like an
advanced google), most scaffolding is boilerplate coming from `laravel new`
command and looking at the `sail` setup and adapting it for the PostgreSQL setup,
I tried to remove most of the unneeded boilerplate but some might've sneaked in there

- I didn't get around to writing unit tests due to work hangups and time constraints,
but I prioritized error handling, full functionality and optimizations (like caching).
