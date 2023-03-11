<?php

namespace AldoBarr\QBittorrent;

use AldoBarr\QBittorrent;
use Psr\Http\Message\ResponseInterface;

class Torrent extends QBittorrent {
	protected string $hash;
	protected string $prefix = 'torrents/';

	protected function __construct() {}

	protected function setHash(string $hash): void {
		$this->hash = $hash;
	}

	public function getHash(): string {
		return $this->hash;
	}

	public function files(string $indexes = ''): array {
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

	public function peers(int $rid = 0): object {
		return $this->sync()->torrentPeers($this->getHash(), $rid);
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
