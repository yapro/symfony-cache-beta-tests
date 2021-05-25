Tests
------------
```sh
docker build -t yapro/symfony-cache-beta-tests:latest -f ./Dockerfile ./
docker run --rm -v $(pwd):/app yapro/symfony-cache-beta-tests:latest bash -c "cd /app \
  && composer install --optimize-autoloader --no-scripts --no-interaction \
  && /app/vendor/bin/simple-phpunit --stderr --stop-on-incomplete --stop-on-failure --stop-on-warning --fail-on-warning --stop-on-risky --fail-on-risky -v /app/tests"
```

Dev
------------
```sh
docker build -t yapro/symfony-cache-beta-tests:latest -f ./Dockerfile ./
docker run -it --rm --net=host -v $(pwd):/app -w /app yapro/symfony-cache-beta-tests:latest bash
composer install -o
PHP_IDE_CONFIG="serverName=common" XDEBUG_SESSION=common XDEBUG_MODE=debug XDEBUG_CONFIG="client_port=9003 max_nesting_level=200" /app/vendor/bin/simple-phpunit /app/tests
```
Если с xdebug что-то не получается, напишите: php -dxdebug.log='/tmp/xdebug.log' и смотрите в лог.

- https://xdebug.org/docs/upgrade_guide
- https://www.jetbrains.com/help/phpstorm/2021.1/debugging-a-php-cli-script.html
