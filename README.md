# QBittorrent PHP API

A PHP client for the qbittorrent WebUI API

## Requirements

- PHP 8.1 or higher

## Installation

Please use Composer for the installation. For Composer documentation, please refer to
[getcomposer.org](http://getcomposer.org/).

Install the module:

```sh
$ composer require aldobarr/qbittorrent-php
```

## Basic Usage

Basic usage will indicate how to create a new api object with login credentials and make a raw request to the api.
All requests using this object will maintain the login state for the life of the object.
The raw request method will return a PSR-7 ResponseInterface object. See [guzzle documentation](https://docs.guzzlephp.org/en/stable/psr7.html#responses).

```php
<?php

require 'vendor/autoload.php';

use AldoBarr\QBittorrent;

$host = 'http://hostname';
$port = 1234;
$username = 'admin';
$password = 'pass';
$qbt = new QBittorrent($host, $port, $username, $password);
$response = $qbt->request('app/version');
echo $response->getBody()->getContents();
```

## Usage

The qbittorrent object allows you to create a more specific api wrapper for interacting with the different APIs available.
For example the basic usage example above may be rewritten as such:

```php
echo $qbt->application()->version();
```

The `application` method will return an Application object which extends the main QBittorrent object and maintains the current login state.
Each child object that implements an api prefix should contain a method that supports each api method under that prefix.

For accessing torrents, you may request a torrent object from the qbt object as such:

```php
$hash = 'torrent-hash';
$torrent = $qbt->torrent($hash);
```

This torrent object may then interact with the torrent API. To get a list of all torrents use the `torrents` method:

```php
// Note: `torrents/info` is implemented in the base qbittorrent class instead of the Torrent class
$torrents = $qbt->torrents();
foreach ($torrents as $torrent_data) {
	$torrent = $qbt->torrent($torrent_data->hash);

	// Get torrent files
	print_r($torrent->files());
}
```

For more information on available API methods, see the main [QBittorrent WebUI Api documentation](https://github.com/qbittorrent/qBittorrent/wiki/WebUI-API-(qBittorrent-4.1)).

## License
This php API wrapper is open-sourced software licensed under the [GNU General Public License version 3](https://opensource.org/license/gpl-3-0/).