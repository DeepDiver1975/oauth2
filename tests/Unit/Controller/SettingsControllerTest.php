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

namespace OCA\OAuth2\Tests\Unit\Controller;

use OCA\OAuth2\AppInfo\Application;
use OCA\OAuth2\Controller\SettingsController;
use OCA\OAuth2\Db\AccessToken;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\AuthorizationCode;
use OCA\OAuth2\Db\AuthorizationCodeMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Db\RefreshToken;
use OCA\OAuth2\Db\RefreshTokenMapper;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase {

	/** @var string $name */
	private $appName;

	/** @var IRequest | \PHPUnit\Framework\MockObject\MockObject */
	private $request;

	/** @var SettingsController $controller */
	private $controller;

	/** @var ClientMapper $clientMapper */
	private $clientMapper;

	/** @var AuthorizationCodeMapper $authorizationCodeMapper */
	private $authorizationCodeMapper;

	/** @var AccessTokenMapper */
	private $accessTokenMapper;

	/** @var RefreshTokenMapper */
	private $refreshTokenMapper;

	/** @var IURLGenerator | MockObject */
	private $urlGenerator;

	/** @var string $userId */
	private $userId = 'john';

	/** @var Client $client */
	private $client;

	/** @var string $redirectUri */
	private $redirectUri = 'https://owncloud.org';

	/** @var string $name */
	private $name = 'ownCloud';

	public function setUp(): void {
		parent::setUp();

		$app = new Application();
		$container = $app->getContainer();

		$this->appName = $container->query('AppName');
		$this->request = $this->createMock(IRequest::class);

		$this->clientMapper = $container->query(ClientMapper::class);
		$this->clientMapper->deleteAll();
		$this->authorizationCodeMapper = $container->query(AuthorizationCodeMapper::class);
		$this->authorizationCodeMapper->deleteAll();
		$this->accessTokenMapper = $container->query(AccessTokenMapper::class);
		$this->accessTokenMapper->deleteAll();
		$this->refreshTokenMapper = $container->query(RefreshTokenMapper::class);
		$this->refreshTokenMapper->deleteAll();

		$client = new Client();
		$client->setIdentifier('NXCy3M3a6FM9pecVyUZuGF62AJVJaCfmkYz7us4yr4QZqVzMIkVZUf1v2IzvsFZa');
		$client->setSecret('9yUZuGF6pecVaCfmIzvsFZakYNXCyr4QZqVzMIky3M3a6FMz7us4VZUf2AJVJ1v2');
		$client->setRedirectUri($this->redirectUri);
		$client->setName($this->name);
		$client->setAllowSubdomains(false);
		$this->client = $this->clientMapper->insert($client);

		$authorizationCode = new AuthorizationCode();
		$authorizationCode->setCode('kYz7us4yr4QZyUZuMIkVZUf1v2IzvsFZaNXCy3M3amqVGF62AJVJaCfz6FM9pecV');
		$authorizationCode->setClientId($this->client->getId());
		$authorizationCode->setUserId($this->userId);
		$authorizationCode->resetExpires();
		$this->authorizationCodeMapper->insert($authorizationCode);

		$accessToken = new AccessToken();
		$accessToken->setToken('3M3amqVGF62kYz7us4yr4QZyUZuMIAZUf1v2IzvsFJVJaCfz6FM9pecVkVZaNXCy');
		$accessToken->setClientId($this->client->getId());
		$accessToken->setUserId($this->userId);
		$accessToken->resetExpires();
		$this->accessTokenMapper->insert($accessToken);

		$refreshToken = new RefreshToken();
		$refreshToken->setToken('3M3amqVGF62kYz7us4yr4QZyUZuMIAZUf1v2IzvsFJVJaCfz6FM9pecVkVZaNXCy');
		$refreshToken->setClientId($this->client->getId());
		$refreshToken->setUserId($this->userId);
		$refreshToken->setAccessTokenId($accessToken->getId());
		$this->refreshTokenMapper->insert($refreshToken);

		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function ($fmt) {
			return $fmt;
		});

		$this->controller = new SettingsController(
			$this->appName,
			$this->request,
			$this->clientMapper,
			$this->authorizationCodeMapper,
			$this->accessTokenMapper,
			$this->refreshTokenMapper,
			$this->userId,
			$l10n,
			$container->query('Logger'),
			$this->urlGenerator
		);
	}

	public function tearDown(): void {
		parent::tearDown();

		$this->clientMapper->deleteAll();
		$this->authorizationCodeMapper->deleteAll();
		$this->accessTokenMapper->deleteAll();
		$this->refreshTokenMapper->deleteAll();
	}

	public function provideClientData(): array {
		return [
			// missing name
			['test', null, null, 0],
			// missing redirect Uri
			[null, 'test', null, 0],
			// malformed redirect Uri
			['test', 'test', null, 0],
			// everything is ok
			[$this->redirectUri, $this->name, null, 1],
			[$this->redirectUri, $this->name, '1', 1]
		];
	}

	/**
	 * @dataProvider provideClientData
	 *
	 * @param string|null $redirectUri
	 * @param string|null $name
	 * @param string|null $allowSubdomains
	 * @param integer $expectedClientCount
	 */
	public function testAddClient($redirectUri, $name, $allowSubdomains, $expectedClientCount): void {
		$this->clientMapper->deleteAll();

		$map = [
			['redirect_uri', '', $redirectUri],
			['name', '', $name],
			['allow_subdomains', null, $allowSubdomains],
			['trusted', null, false],
		];
		$this->request->method('getParam')->willReturnMap($map);
		$result = $this->controller->addClient();
		self::assertInstanceOf(JSONResponse::class, $result);
		self::assertCount($expectedClientCount, $this->clientMapper->findAll());
		if ($expectedClientCount === 1) {
			/** @var Client $client */
			$client = $this->clientMapper->findAll()[0];
			self::assertEquals($redirectUri, $client->getRedirectUri());
			self::assertEquals($name, $client->getName());
			self::assertEquals((bool) $allowSubdomains, $client->getAllowSubdomains());
		}
	}

	public function provideClientId(): array {
		// Data providers are run before the class is configured.
		// That's why we need a closure if we want to access $this->smth->property
		return [
			[function ($obj) {
				return null;
			}, 'error', 1],
			[function ($obj) {
				return 'test';
			}, 'error', 1],
			[function ($obj) {
				return $obj->client === null ? '' : $obj->client->getId();
			}, 'success', 0
			]
		];
	}

	/**
	 * @dataProvider provideClientId
	 *
	 * @param callable $clientIdProvider
	 * @param string $expectedStatus
	 * @param integer $expectedClientCount
	 */
	public function testDeleteClient($clientIdProvider, $expectedStatus, $expectedClientCount): void {
		$result = $this->controller->deleteClient($clientIdProvider($this));
		self::assertInstanceOf(JSONResponse::class, $result);
		$responseData = $result->getData();
		self::assertEquals($expectedStatus, $responseData['status']);
		self::assertCount($expectedClientCount, $this->clientMapper->findAll());
	}

	public function testRevokeAuthorization(): void {
		$this->urlGenerator->method('linkToRouteAbsolute')->willReturn('/personal');

		$result = $this->controller->revokeAuthorization(null, null);
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals('/personal#' . $this->appName, $result->getRedirectURL());
		self::assertCount(1, $this->clientMapper->findAll());
		self::assertCount(1, $this->authorizationCodeMapper->findAll());
		self::assertCount(1, $this->accessTokenMapper->findAll());
		self::assertCount(1, $this->refreshTokenMapper->findAll());

		$result = $this->controller->revokeAuthorization('', '');
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals('/personal#' . $this->appName, $result->getRedirectURL());
		self::assertCount(1, $this->clientMapper->findAll());
		self::assertCount(1, $this->authorizationCodeMapper->findAll());
		self::assertCount(1, $this->accessTokenMapper->findAll());
		self::assertCount(1, $this->refreshTokenMapper->findAll());

		$result = $this->controller->revokeAuthorization(12, 12);
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals('/personal#' . $this->appName, $result->getRedirectURL());
		self::assertCount(1, $this->clientMapper->findAll());
		self::assertCount(1, $this->authorizationCodeMapper->findAll());
		self::assertCount(1, $this->accessTokenMapper->findAll());
		self::assertCount(1, $this->refreshTokenMapper->findAll());

		$result = $this->controller->revokeAuthorization($this->client->getId(), $this->userId);
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals('/personal#' . $this->appName, $result->getRedirectURL());
		self::assertCount(1, $this->clientMapper->findAll());
		self::assertCount(0, $this->authorizationCodeMapper->findAll());
		self::assertCount(0, $this->accessTokenMapper->findAll());
		self::assertCount(0, $this->refreshTokenMapper->findAll());
	}

	public function healthDataProvider() {
		return [
			['someToken', ['authHeaderFound' => true]],
			[null, ['authHeaderFound' => false]]
		];
	}

	/**
	 * @dataProvider healthDataProvider
	 * @param string $authHeader
	 * @param array $expectedResult
	 */
	public function testTest($authHeader, $expectedResult) {
		$this->request->method('getHeader')->willReturn($authHeader);
		$result = $this->controller->test();
		$this->assertEquals($result, $expectedResult);
	}
}
