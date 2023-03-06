<?php

namespace AldoBarr\QBittorrent;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class Torrent {
	private Client $client;
	private string $hash;
	private string $prefix = 'torrents/';

	public function __construct(Client $client, string $hash) {
		$this->client = $client;
		$this->hash = $hash;
	}

	public function files(string $indexes = ''): ?array {
		$options = ['query' => ['hash' => $this->hash]];
		if (!empty($indexes)) {
			$options['query']['indexes'] = $indexes;
		}

		$response = $this->client->get($this->prefix . 'files', $options);
		$body = $response->getBody();
		$data = json_decode($body->getContents());
		$body->close();

		return $data;
	}

	public function renameFile(string $old_path, string $new_path): ResponseInterface {
		return $this->client->post($this->prefix . 'renameFile', [
			'form_params' => [
				'hash' => $this->hash,
				'oldPath' => $old_path,
				'newPath' => $new_path
			]
		]);
	}

	public function renameFolder(string $old_path, string $new_path): ResponseInterface {
		return $this->client->post($this->prefix . 'renameFolder', [
			'form_params' => [
				'hash' => $this->hash,
				'oldPath' => $old_path,
				'newPath' => $new_path
			]
		]);
	}
}
