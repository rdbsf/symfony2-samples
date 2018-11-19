<?php

namespace Acme\ApiBundle\Tests\Controller;

use Acme\ApiBundle\Entity\Location;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;

use Aws\S3\S3Client;

class UserControllerTest extends AcmeTestCase {

    private static function RandomString()
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $randstring = '';
        for ($i = 0; $i < 5; $i++) {
            $randstring .= $characters[rand(0, strlen($characters)-1)];
        }
        return $randstring;
    }

    public function createTestUser() {

        $container = $this->client->getContainer();

        $user = $this->userManager->createUser();

        $user->setEmail('test_'.self::RandomString().'@user.com');
        $user->setUsername('test_'.self::RandomString().'@user.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPlainPassword('test');
        $user->setZipCode('94134');
        $this->userManager->updateUser($user);

        /** @var $loginManager \FOS\UserBundle\Security\LoginManager */
        $loginManager = $container->get('fos_user.security.login_manager');
        $firewallName = $container->getParameter('fos_user.firewall_name');
        $loginManager->loginUser($firewallName, $user);


        /** @var JWTManager $jwtManager */
        $this->jwtManager = $container->get('lexik_jwt_authentication.jwt_manager');

        $this->jwt = $this->jwtManager->create($user);

        $em = $container->get('Acme_api.Acme_api_service');

        $em->createUser($user->getId(), $user->getFirstName(), $user->getLastName(), $user->getZipCode());

        $this->user = $user;
    }

    /**
     * Test user can access self via GET api
     */
    public function testGetMe()
    {
        $this->getUserWithDID();

        $crawler = $this->client->request('GET', '/api/me', array(), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));
        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 200);

        $content = $response->getContent();
        $this->assertContains('"first_name":"'.$this->user->getFirstName(), $content);

        $decoded = json_decode($content, true);
        $this->assertTrue(isset($decoded['votes']));
    }

    /**
     * Test adding to a public list
     */
    public function testActiveUserAddPublicList()
    {
        $this->getUserWithDID();

        // @TODO random google volume id to help testing with new books
        $crawler = $this->client->request('POST', '/api/list/add/book?list_id='.self::PUBLIC_LIST_ID.'&volume_id='.self::PUBLIC_LIST_ADD_VOLUME_ID, array('list_id' => '714', 'volume_id' => 'hPpYUAuykoEC'), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));
        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 200);

        $content = $response->getContent();
        $this->assertContains('Book Added', $content);
    }

    /**
     * Test that user is not allowed to add/vote same book in same list
     */
    public function testActiveUserAddSamePublicList()
    {
        $this->getUserWithDID();

        // adding same book again
        $crawler = $this->client->request('POST', '/api/list/add/book?list_id='.self::PUBLIC_LIST_ID.'&volume_id='.self::PUBLIC_LIST_ADD_VOLUME_ID, array('list_id' => '714', 'volume_id' => 'hPpYUAuykoEC'), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));
        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 403);

        $content = $response->getContent();
        $this->assertContains('User already voted', $content);
    }

    /**
     * Test that user is not allowed to add to a private list
     */
    public function testActiveUserAddPrivateList()
    {
        $this->getUserWithDID();

        $crawler = $this->client->request('POST', '/api/list/add/book?list_id='.self::PRIVATE_LIST_ID.'&volume_id='.self::PRIVATE_LIST_ADD_VOLUME_ID, array('list_id' => '725', 'volume_id' => 'hPpYUAuykoEC'), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));
        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 403);

        $content = $response->getContent();
        $this->assertContains('Book List is private', $content);
    }


    /**
     * Test that user is not allowed to edit a list you are owner of, all details
     */
    public function testPostListEdit()
    {
        $this->getUserWithDID();
        $randomListName = self::RandomString();

        $testListId = self::UNIT_USER_DID_OWNER_LIST_ID;
        $newListPublic = 'true';
        $newListName = 'My New List ' . $randomListName;
        $newListDesc = 'This is a test list ' . $randomListName;
        $newListGenre = 'Genre ' . $randomListName;

        $crawler = $this->client->request('POST', '/api/list/edit?list_id='.$testListId.'&list_name='.$newListName.'&list_description='.$newListDesc.'&list_genre='.$newListGenre.'&list_public='.$newListPublic, array(), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));
        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 200);
        $content = $response->getContent();


        $decoded = json_decode($content, true);
        $this->assertTrue($decoded['listDetails']['name'] == $newListName);
        $this->assertTrue($decoded['listDetails']['description'] == $newListDesc);
        $this->assertTrue($decoded['listDetails']['genre'] == $newListGenre);
        $this->assertTrue($decoded['listDetails']['type'] == 'PUBLIC');

    }

    /**
     * Test not allowed to edit a list you dont own
     */
    public function testPostListEditNonOwner()
    {
        $this->getUserWithDID();
        $randomListName = self::RandomString();

        $testListId = self::UNIT_USER_DID_NOT_OWNER_LIST_ID;
        $newListPublic = 'true';
        $newListName = 'My New List ' . $randomListName;
        $newListDesc = 'This is a test list ' . $randomListName;
        $newListGenre = 'Genre ' . $randomListName;

        $crawler = $this->client->request('POST', '/api/list/edit?list_id='.$testListId.'&list_name='.$newListName.'&list_description='.$newListDesc.'&list_genre='.$newListGenre.'&list_public='.$newListPublic, array(), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));
        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 403);
    }

    /**
     * Test browsing user public lists
     */
    public function testGetBrowseUserLists()
    {
        $crawler = $this->client->request('GET', '/api/browse/user/lists', array('user_id' => self::UNIT_USER_DID_USER_ID), array(), array('HTTP_ACCEPT' => 'application/json'));

        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 200);
        $content = $response->getContent();

        $this->assertContains(self::UNIT_USER_DID_DISPLAY_NAME, $content);
    }

    /**
     * Test we can browser the books a user has added
     */
    public function testGetBrowseUserBooks()
    {

        $crawler = $this->client->request('GET', '/api/browse/user/books', array('user_id' => self::UNIT_USER_DID_USER_ID), array(), array('HTTP_ACCEPT' => 'application/json'));

        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 200);
        $content = $response->getContent();

        $this->assertContains(self::UNIT_USER_DID_BOOK_ADD_TITLE, $content);
    }

    /**
     * Testing we can view a users reviews
     */
    public function testGetBrowseUserReviews()
    {
        $crawler = $this->client->request('GET', '/api/browse/user/reviews', array('user_id' => self::UNIT_USER_DID_USER_ID), array(), array('HTTP_ACCEPT' => 'application/json'));

        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 200);
        $content = $response->getContent();

        $this->assertContains(self::UNIT_USER_REVIEW_TEXT, $content);
    }


    /**
     * Test adding a location to database given valid coordinate, data
     */
    public function testPostLocationAdd()
    {
        $type = Location::LOCATION_TYPE_BOOKSTORE;
        $name = 'Russian Hill Bookstore';
        $coords = '37.7589637,-122.421664';
        $placeId = 'ChIJPTJ-sumAhYARCTvXNWrVpQE';
        $vicinity = '2234 Polk Street, San Francisco';

        $this->getUserWithDID();

        $crawler = $this->client->request('POST', '/api/location/add?type='.$type.'&name='.$name.'&coords='.$coords.'&place_id='.$placeId.'&vicinity='.$vicinity, array('type' => Location::LOCATION_TYPE_BOOKSTORE), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));

        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 200);

        $content = $response->getContent();

        $decoded = json_decode($content, true);
        $this->assertTrue(isset($decoded['location']) && isset($decoded['location']['id']));

    }


    /**
     * Test account exists by submitting duplicate email address
     */
    public function testPostAccountExistingEmail()
    {
        $firstName = 'First';
        $lastName = 'Last';
        $bio = 'My bio';
        $zipCode = '94134';
        $email = 'test10@test.com'; // this email exists
        $passwordOld = 'test';
        $passwordNew = 'test';

        $args = 'first_name='.$firstName.'&last_name='.$lastName.'&bio='.$bio.'&zip_code='.$zipCode.'&email='.$email.'&current_password='.$passwordOld.'&password_new='.$passwordNew;

        $crawler = $this->client->request('POST', '/user/register?' . $args, array(), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));

        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 401);
        $content = $response->getContent();

        $decoded = json_decode($content, true);
        $this->assertTrue(isset($decoded['errors']));

    }

    /**
     * Test submitted passwords match
     */
    public function testPostAccountCurrentPasswordMatch()
    {
        $firstName = 'First';
        $lastName = 'Last';
        $bio = 'My bio';
        $zipCode = '94134';
        $email = 'unit@test.com';
        $passwordOld = 'WRONG'; // incorrect password
        $passwordNew = 'test';

        $args = 'first_name='.$firstName.'&last_name='.$lastName.'&bio='.$bio.'&zip_code='.$zipCode.'&email='.$email.'&current_password='.$passwordOld.'&password_new='.$passwordNew;

        $crawler = $this->client->request('POST', '/user/register?' . $args, array(), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));

        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 401);
        $content = $response->getContent();

        $decoded = json_decode($content, true);
        $this->assertTrue(isset($decoded['errors']));

        $this->assertContains('password', $content);

    }


}