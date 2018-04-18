<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Comments\Search;


use OCP\Comments\IComment;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IUser;

class Provider extends \OCP\Search\Provider {

	/**
	 * Search for $query
	 *
	 * @param string $query
	 * @return array An array of OCP\Search\Result's
	 * @since 7.0.0
	 */
	public function search($query): array {
		$cm = \OC::$server->getCommentsManager();
		$us = \OC::$server->getUserSession();
		$um = \OC::$server->getUserManager();

		$user = $us->getUser();
		if (!$user instanceof IUser) {
			return [];
		}
		$uf = \OC::$server->getUserFolder($user->getUID());

		if ($uf === null) {
			return [];
		}

		/** @var IComment[] $comments */
		$comments = $cm->search('files', '', 'comment', $query);


		$result = [];
		foreach ($comments as $comment) {
			if ($comment->getActorType() !== 'users') {
				continue;
			}

			$author = $um->get($comment->getActorId());

			if (!$author instanceof IUser) {
				continue;
			}

			try {
				$file = $this->getFileForComment($uf, $comment);
				$result[] = new CommentSearchResult($query,
					(int) $comment->getId(),
					$comment->getMessage(),
					$author->getUID(),
					$author->getDisplayName(),
					$file->getPath()
				);
			} catch (NotFoundException $e) {
				continue;
			}
		}

		return $result;
	}

	/**
	 * @param Folder $userFolder
	 * @param IComment $comment
	 * @return Node
	 * @throws NotFoundException
	 */
	protected function getFileForComment(Folder $userFolder, IComment $comment): Node {
		$nodes = $userFolder->getById((int) $comment->getObjectId());
		if (empty($nodes)) {
			throw new NotFoundException('File not found');
		}

		return array_shift($nodes);
	}
}