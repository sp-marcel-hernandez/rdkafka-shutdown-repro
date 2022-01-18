## Setup

```
$ composer install --ignore-platform-reqs -a
$ docker-compose run --rm dadarek
```

Put this in `$HOME/curl-format.txt`:
```
    time_namelookup:  %{time_namelookup}\n
       time_connect:  %{time_connect}\n
    time_appconnect:  %{time_appconnect}\n
   time_pretransfer:  %{time_pretransfer}\n
 time_starttransfer:  %{time_starttransfer}\n
                    ----------\n
         time_total:  %{time_total}\n\n
```

```
$ curl -s -w@$HOME/curl-format.txt localhost -o /dev/null
    time_namelookup:  0.004197
       time_connect:  0.004323
    time_appconnect:  0.000000
   time_pretransfer:  0.004371
 time_starttransfer:  3.800695
                    ----------
         time_total:  3.902166


$ docker-compose logs -f php-fpm
...
php-fpm_1    | NOTICE: PHP message: produce 100000 messages (ms): 401.9
php-fpm_1    | NOTICE: PHP message: flush (ms): 3389.6
php-fpm_1    | NOTICE: PHP message: handler (ms): 3793
php-fpm_1    | NOTICE: PHP message: all (ms): 3794.7
```

Shutdown time: Difference between `all (ms)` in php-fpm log and `(time_starttransfer - time_pretransfer)`


## Versions

* PHP 7.3.33
* php-rdkafka 5.0.2
* librdkafka 0.11.3
