<?php
/**
 * @author Project Seminar "sciebo@Learnweb" of the University of Muenster
 * @copyright Copyright (c) 2017, University of Muenster
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

namespace OCA\OAuth2\Hooks;

use OC\User\Manager;
use OC\User\User;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\AuthorizationCodeMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Db\RefreshTokenMapper;
use OCP\ILogger;
use OCP\IUserSession;

class UserHooks {
	/** @var Manager */
	private $userManager;

	/** @var AuthorizationCodeMapper */
	private $authorizationCodeMapper;

	/** @var  AccessTokenMapper */
	private $accessTokenMapper;

	/** @var RefreshTokenMapper */
	private $refreshTokenMapper;

	/** @var ClientMapper */
	private $clientMapper;

	/** @var ILogger */
	private $logger;

	/** @var string */
	private $appName;

	/** @var IUserSession */
	private $userSession;

	/**
	 * UserHooks constructor.
	 *
	 * @param Manager $userManager The user manager.
	 * @param AuthorizationCodeMapper $authorizationCodeMapper The authorization code mapper.
	 * @param AccessTokenMapper $accessTokenMapper The access token mapper.
	 * @param RefreshTokenMapper $refreshTokenMapper The refresh token mapper.
	 * @param ClientMapper $clientMapper The client mapper.
	 * @param ILogger $logger The logger.
	 * @param IUserSession $userSession The user session
	 * @param string $AppName The app's name.
	 */
	public function __construct(
		Manager $userManager,
		AuthorizationCodeMapper $authorizationCodeMapper,
		AccessTokenMapper $accessTokenMapper,
		RefreshTokenMapper $refreshTokenMapper,
		ClientMapper $clientMapper,
		ILogger $logger,
		IUserSession $userSession,
		$AppName
	) {
		$this->userManager = $userManager;
		$this->authorizationCodeMapper = $authorizationCodeMapper;
		$this->accessTokenMapper = $accessTokenMapper;
		$this->refreshTokenMapper = $refreshTokenMapper;
		$this->clientMapper = $clientMapper;
		$this->logger = $logger;
		$this->userSession = $userSession;
		$this->appName = $AppName;
	}

	/**
	 * Registers a pre-delete hook for users to delete authorization codes,
	 * access tokens and refresh tokens that reference the user.
	 */
	public function register() {
		/**
		 * @param User $user .
		 */
		$callback = function ($user) {
			if ($user->getUID() !== null) {
				$this->logger->info('Deleting authorization codes, access tokens and refresh tokens referencing the user to be deleted "' . $user->getUID() . '".', ['app' => $this->appName]);

				$this->authorizationCodeMapper->deleteByUser($user->getUID());
				$this->accessTokenMapper->deleteByUser($user->getUID());
				$this->refreshTokenMapper->deleteByUser($user->getUID());
			}
		};
		/** @phan-suppress-next-line PhanUndeclaredMethod */
		$this->userManager->listen('\OC\User', 'preDelete', $callback);
		$this->userManager->listen('\OC\User', 'preLogout', function () {
			$user = $this->userSession->getUser();
			$clientsWithInvalidate = $this->clientMapper->findInvalidateOnLogout();

			foreach ($clientsWithInvalidate as $client) {
				$this->authorizationCodeMapper->deleteByClientUser($client->getId(), $user->getUID());
				$this->accessTokenMapper->deleteByClientUser($client->getId(), $user->getUID());
				$this->refreshTokenMapper->deleteByClientUser($client->getId(), $user->getUID());
			}

			return null;
		});
	}
}
