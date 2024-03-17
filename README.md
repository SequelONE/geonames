# geonames v9.x


[![Latest Stable Version](https://poser.pugx.org/sequelone/geonames/version)](https://packagist.org/packages/sequelone/geonames)  [![Total Downloads](https://poser.pugx.org/sequelone/geonames/downloads)](https://packagist.org/packages/sequelone/geonames)  [![License](https://poser.pugx.org/sequelone/geonames/license)](https://packagist.org/packages/sequelone/geonames) [![GitHub issues](https://img.shields.io/github/issues/sequelone/geonames)](https://github.com/sequelone/geonames/issues) [![GitHub forks](https://img.shields.io/github/forks/sequelone/geonames)](https://github.com/sequelone/geonames/network) [![GitHub stars](https://img.shields.io/github/stars/sequelone/geonames)](https://github.com/sequelone/geonames/stargazers) ![Travis (.org)](https://img.shields.io/travis/sequelone/geonames)  

A Laravel (php) package to interface with the geo-location services at geonames.org.

## Major Version Jump
I jumped several major versions to catch up with Larvel's major version number. Makes things a little clearer.

## Notes
There is still a lot that needs to be done to make this package "complete". I've gotten it to a point where I can use it for my next project. As time allows, I will improve the documentation and testing that comes with this package. Thanks for understanding.

## Installation
```
composer require sequelone/geonames
```
And then add `geonames` provider to `providers` array in `app.php` config file:

```php
SequelONE\Geonames\GeonamesServiceProvider::class,
```

After that, Run migrate command:

```
php artisan migrate
```
## Settings
Add in .env

```
DB_PREFIX=
```

Add or edit in database driver `config/database.php`:
```
'prefix' => env('DB_PREFIX', ''),
```

and

```
'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            ]) : [],
```

Do not forget to include local_infile in MySQL:

```
SHOW GLOBAL VARIABLES LIKE 'local_infile';
SET GLOBAL local_infile = 'ON';
SHOW GLOBAL VARIABLES LIKE 'local_infile';
```

It should echo the following:

```
mysql> SHOW GLOBAL VARIABLES LIKE 'local_infile';
+---------------+-------+
| Variable_name | Value |
+---------------+-------+
| local_infile  | OFF   |
+---------------+-------+
1 row in set (0.00 sec)

mysql> SET GLOBAL local_infile = 'ON';
Query OK, 0 rows affected (0.06 sec)

mysql> SHOW GLOBAL VARIABLES LIKE 'local_infile';
+---------------+-------+
| Variable_name | Value |
+---------------+-------+
| local_infile  | ON    |
+---------------+-------+
1 row in set (0.00 sec)

mysql>
```

or if setting this in my.cnf:
```
[mysqld]
local_infile=ON
```

https://dba.stackexchange.com/questions/48751/enabling-load-data-local-infile-in-mysql

Want to install all of the geonames records for the US, Canada, and Mexico as well as pull in the feature codes 
definitions file in English? 
```php
php artisan geonames:install --country=US --country=CA --country=MX --language=en
```

Want to just install everything in the geonames database?
```php
php artisan geonames:install
```

## Maintenance
Now that you have the geonames database up and running on your system, you need to keep it up-to-date.

I have an update script that you need to schedule in Laravel to run every day.

Some info on how to schedule Laravel artisan commands:

https://laravel.com/docs/5.6/scheduling#scheduling-artisan-commands

You can read this notice at: http://download.geonames.org/export/dump/

<code>The "last modified" timestamp is in Central European Time. </code>

It looks like geonames updates their data around 3AM CET.

So if you schedule your system to run the geonames:update artisan command after 4AM CET, you should be good to go.

I like to keep my servers running on GMT. Keeps things consistent.

(Central European Time is 1 hour ahead of Greenwich Mean Time)

Assuming your servers are running on GMT, your update command would look like: 
```php
$schedule->command('geonames:update')->dailyAt('3:00');
```

The update artisan command will handle the updates and deletes to the geonames table.

By default, `GeonamesServiceProvider` will run it for you daily at `config('geonames.update_daily_at')`. 

## Gotchas
Are you getting something like: 1071 Specified key was too long

@see https://laravel-news.com/laravel-5-4-key-too-long-error

Add this to your AppServiceProvider.php file:
```php
Schema::defaultStringLength(191);
```

## A quick word on indexes

This library contains a bunch of migrations that contain a bunch of indexes. Now not everyone will need all of the indexes.

So when you install this library, run the migrations and delete the indexes that you don't need.

Also, Laravel doesn't let you specify a key length for indexes on varchar columns. There are two indexes suffering from this limit. Instead of creating indexes on those columns the "Laravel way", I send a raw/manual query to create the indexes with the proper lengths.
