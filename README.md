
```
$ composer install --ignore-platform-reqs
$ docker-compose run --rm dadarek
$ docker-compose logs -f php-fpm
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
$ curl -s -w@/home/socialpoint/curl-format.txt localhost -o /dev/null
    time_namelookup:  0.004157
       time_connect:  0.004266
    time_appconnect:  0.000000
   time_pretransfer:  0.004293
 time_starttransfer:  0.256332
                    ----------
         time_total:  0.256409
```

Compare `(time_starttransfer - time_pretransfer)` to `all (ms)` in php-fpm log
