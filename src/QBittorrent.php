<?php

namespace AldoBarr;

use AldoBarr\QBittorrent\Application;
use AldoBarr\QBittorrent\Torrent;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

class QBittorrent {
	public const MIN_API_VERSION = '2.8.3';

	private Client $client;
	private string $username;
	private string $password;
	private bool $authenticated = false;

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

		try {
			$version = $this->application()->apiVersion();
			if (empty($version)) {
				throw new \Exception;
			} else if (!version_compare($version, self::MIN_API_VERSION, '>=')) {
				throw new \Exception($version);
			}
		} catch (\Throwable $t) {
			$version = $t->getMessage();
			$msg = 'Your version of the qbittorrent WebAPI is not supported';
			if (!empty($version)) {
				$msg = "Your version of the qbittorrent WebAPI ({$version}) is lower than the minimum supported version of " . self::MIN_API_VERSION;
			}

			throw new \AldoBarr\QBittorrent\Exceptions\InvalidVersionException($msg);
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
		return new Application($this->client, $this->authenticated);
	}

	public function torrents(array $options = []): array {
		$response = $this->client->get('torrents/info', ['query' => $options]);
		$body = $response->getBody();
		$data = json_decode($body->getContents());
		$body->close();

		return $data;
	}

	public function torrent(string $hash): Torrent {
		return new Torrent($this->client, $hash);
	}
}
