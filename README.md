

## Requirements

* PHP 7.4
* MySQL 5.7
* [Composer](http://getcomposer.org/)

## Installation

From your project's root directory run the following command:

```
composer install
```

## Configuration

Make sure to define your database connection in `.env` file 
(you need rename `.env.example` to `.env`).

Then run the provided migration:

```
php bin/console migrate
```
