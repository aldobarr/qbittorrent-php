<?php

namespace AldoBarr\QBittorrent;

use GuzzleHttp\Client;

class Application {
	private Client $client;
	private string $prefix = 'app/';
	private bool $authenticated;

	public function __construct(Client $client, bool &$authenticated) {
		$this->client = $client;
		$this->authenticated = &$authenticated;
	}

	public function version(): string {
		try {
			$response = $this->client->get($this->prefix . 'version');
			$body = $response->getBody();
			$version = $body->getContents();
			$body->close();

			if (strcasecmp(substr($version, 0, 1), 'v') === 0) {
				$version = substr($version, 1);
			}

			return $version;
		} catch (\Throwable) {}

		return '';
	}

	public function apiVersion(): string {
		try {
			$response = $this->client->get($this->prefix . 'webapiVersion');
			$body = $response->getBody();
			$version = $body->getContents();
			$body->close();

			if (strcasecmp(substr($version, 0, 1), 'v') === 0) {
				$version = substr($version, 1);
			}

			return $version;
		} catch (\Throwable) {}

		return '';
	}

	public function buildInfo(): object {
		$response = $this->client->get($this->prefix . 'webapiVersion');
		$body = $response->getBody();
		$data = json_decode($body->getContents());
		$body->close();

		return $data;
	}

	public function shutdown(): bool {
		$response = $this->client->post($this->prefix . 'shutdown');
		$success = $response->getStatusCode() === 200;
		if ($success) {
			$this->authenticated = false;
		}

		return $success;
	}
}
