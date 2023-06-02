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

namespace OCA\OAuth2\Tests\Unit;

use OC\Core\Application;
use OCA\OAuth2\AuthModule;
use OCA\OAuth2\Db\AccessToken;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCP\IUserManager;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuthModuleTest extends TestCase {
	/** @var IUserManager $userManager */
	private $userManager;

	/** @var String $userId */
	private $userId = 'john';

	/** @var String $userIdConcat */
	private $userIdConcat = 'John Doe:john';

	/** @var ClientMapper $clientMapper */
	private $clientMapper;

	/** @var AccessTokenMapper */
	private $accessTokenMapper;

	/** @var Client */
	private $client;

	/** @var AccessToken */
	private $accessToken;

	/** @var AuthModule $authModule */
	private $authModule;

	/**
	 * @throws \Exception
	 */
	public function setUp(): void {
		parent::setUp();

		$app = new Application();
		$container = $app->getContainer();

		$this->userManager = $container->query('UserManager');
		$this->userManager->createUser($this->userId, 'pass');

		$this->clientMapper = $container->query(ClientMapper::class);
		$this->accessTokenMapper = $container->query(AccessTokenMapper::class);

		$client = new Client();
		$client->setIdentifier('NXCy3M3a6FM9pecVyUZuGF62AJVJaCfmkYz7us4yr4QZqVzMIkVZUf1v2IzvsFZa');
		$client->setSecret('9yUZuGF6pecVaCfmIzvsFZakYNXCyr4QZqVzMIky3M3a6FMz7us4VZUf2AJVJ1v2');
		$client->setRedirectUri('https://owncloud.org');
		$client->setName('ownCloud');
		$this->client = $this->clientMapper->insert($client);

		/** @var AccessToken $accessToken */
		$accessToken = new AccessToken();
		$accessToken->setToken('sFz6FM9pecGF62kYz7us43M3amqVZaNQZyUZuMIkAJVJaCfVyr4Uf1v2IzvVZXCy');
		$accessToken->setClientId($client->getId());
		$accessToken->setUserId($this->userId);
		$accessToken->resetExpires();
		$this->accessToken = $this->accessTokenMapper->insert($accessToken);

		$this->authModule = new AuthModule();
	}

	protected function tearDown(): void {
		parent::tearDown();

		$this->clientMapper->deleteAll();
		$this->accessTokenMapper->deleteAll();
		$this->userManager->get($this->userId)->delete();
	}

	/**
	 * @throws \Exception
	 */
	public function testAuth() {
		// Wrong Authorization header
		/** @var IRequest | MockObject $request */
		$request = $this->getMockBuilder(IRequest::class)->getMock();
		$request->expects($this->once())
			->method('getHeader')
			->with($this->equalTo('Authorization'))
			->will($this->returnValue('Basic sFz6FM9pecGF.62kYz7us43M3am'));
		$this->assertNull($this->authModule->auth($request));

		// Valid request
		$request = $this->getMockBuilder(IRequest::class)->getMock();
		$request->expects($this->once())
			->method('getHeader')
			->with($this->equalTo('Authorization'))
			->will($this->returnValue('Bearer ' . $this->accessToken->getToken()));
		$user = $this->authModule->auth($request);
		$this->assertNotNull($user);
		$this->assertEquals($this->userId, $user->getUID());

		// Valid request with ConcatUserID
		$request = $this->getMockBuilder(IRequest::class)->getMock();
		$this->accessToken->setUserId($this->userIdConcat);
		$this->accessToken = $this->accessTokenMapper->update($this->accessToken);
		$request->expects($this->once())
			->method('getHeader')
			->with($this->equalTo('Authorization'))
			->will($this->returnValue('Bearer ' . $this->accessToken->getToken()));
		$user = $this->authModule->auth($request);
		$this->assertNotNull($user);
		$this->assertEquals($this->userId, $user->getUID());
	}

	/**
	 * @throws \Exception
	 */
	public function testExpiredToken() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid token');

		$this->accessToken->setExpires(\time() - 1);
		$this->accessTokenMapper->update($this->accessToken);
		/** @var IRequest | MockObject $request */
		$request = $this->getMockBuilder(IRequest::class)->getMock();
		$request->expects($this->once())
			->method('getHeader')
			->with($this->equalTo('Authorization'))
			->will($this->returnValue('Bearer ' . $this->accessToken->getToken()));
		$this->authModule->auth($request);
	}

	/**
	 * @throws \Exception
	 */
	public function testInvalidToken() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid token');

		/** @var IRequest | MockObject $request */
		$request = $this->getMockBuilder(IRequest::class)->getMock();
		$request->expects($this->once())
			->method('getHeader')
			->with($this->equalTo('Authorization'))
			->willReturn('Bearer test');
		$this->authModule->auth($request);
	}

	public function testGetUserPassword() {
		/** @var IRequest | MockObject $request */
		$request = $this->getMockBuilder(IRequest::class)->getMock();
		$this->assertEquals('', $this->authModule->getUserPassword($request));
	}
}
