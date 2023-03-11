<?php

/**
 *  qbittorrent-php - PHP QBittorrent API Wrapper
 *  Copyright (C) 2023  Aldo Barreras
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace AldoBarr;

use AldoBarr\QBittorrent\Application;
use AldoBarr\QBittorrent\Contracts\Api;
use AldoBarr\QBittorrent\Log;
use AldoBarr\QBittorrent\Torrent;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class QBittorrent implements Api {
	public const API_VERSION_SUPPORT = '2.8.3';

	protected Client $client;
	protected string $username;
	protected string $password;
	protected string $version;
	protected bool $authenticated = false;

	public function __construct(string $url, int $port, string $username, string $password) {
		$parsed_url = parse_url($url);
		if (empty($parsed_url['scheme']) || empty($parsed_url['host'])) {
			throw new \AldoBarr\QBittorrent\Exceptions\InvalidUriException('Invalid URL');
		}

		$this->username = $username;
		$this->password = $password;

		$qbt = &$this;
		$stack = new HandlerStack;
		$stack->setHandler(new CurlHandler);
		$stack->push(Middleware::mapRequest(function(RequestInterface $request) use (&$qbt) {
			$path = $request->getUri()->getPath();
			if (strcmp(substr($path, -10), 'auth/login') === 0 || strcmp(substr($path, -11), 'auth/logout') === 0) {
				return $request;
			}

			if (!$qbt->isLoggedIn() && !$qbt->login()) {
				throw new \AldoBarr\QBittorrent\Exceptions\AuthFailedException('Unable to login to qbittorrent api');
			}

			return $request;
		}));

		$this->client = new Client([
			'cookies' => true,
			'http_errors' => false,
			'handler' => $stack,
			'base_uri' => $parsed_url['scheme'] . '://' . $parsed_url['host'] . ':' . $port . '/api/v2/'
		]);

		if (!$this->login()) {
			throw new \AldoBarr\QBittorrent\Exceptions\AuthFailedException('Unable to login to qbittorrent api');
		}

		$this->version = $this->application()->apiVersion();
		if (empty($this->version)) {
			throw new \AldoBarr\QBittorrent\Exceptions\InvalidVersionException('Your version of the qbittorrent WebAPI is not supported');
		}
	}

	public function __destruct() {
		if (!$this->logout()) {
			throw new \AldoBarr\QBittorrent\Exceptions\AuthFailedException('Unable to logout of qbittorrent api');
		}
	}

	public function isLoggedIn() {
		return $this->authenticated;
	}

	public function login(): bool {
		if (!$this->authenticated) {
			try {
				$response = $this->client->post('auth/login', [
					'form_params' => [
						'username' => $this->username,
						'password' => $this->password
					]
				]);

				$this->authenticated = $response->getStatusCode() === 200;
				return $this->authenticated;
			} catch (\Throwable) {}

			return false;
		}

		return true;
	}

	public function logout(): bool {
		if ($this->authenticated) {
			try {
				$response = $this->client->post('auth/logout', ['form_params' => []]);
				$this->authenticated = !($response->getStatusCode() === 200);
				return !$this->authenticated;
			} catch (\Throwable) {}

			return false;
		}

		return true;
	}

	protected function setAuthenticated(bool $authed): void {
		$this->authenticated = $authed;
	}

	public function application(): Application {
		$application = new Application;
		return $this->cloneApiObject($application);
	}

	public function log(): Log {
		$log = new Log;
		return $this->cloneApiObject($log);
	}

	public function request(string $endpoint, string $method = 'GET', array $options = []): ResponseInterface {
		return $this->client->request($method, $endpoint, $options);
	}

	public function torrents(array $options = []): array {
		$response = $this->client->get('torrents/info', ['query' => $options]);
		$body = $response->getBody();
		$data = json_decode($body->getContents());
		$body->close();

		return $data;
	}

	public function torrent(string $hash): Torrent {
		$torrent = new Torrent;
		$this->cloneApiObject($torrent);
		$torrent->setHash($hash);
		return $torrent;
	}

	private function &cloneApiObject(Api &$new_object): Api {
		$options = array_keys(get_object_vars($this));
		foreach ($options as $option) {
			$new_object->{$option} = &$this->{$option};
		}

		return $new_object;
	}
}
