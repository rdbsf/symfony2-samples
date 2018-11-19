<?php

namespace Acme\ApiBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookControllerTest extends AcmeTestCase
{
    /**
     * Ability to browse book details
     */
    public function testGetBrowseBook()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/api/browse/book', array('book_id' => '582'), array(), array('HTTP_ACCEPT' => 'application/json'));

        $response = $client->getResponse();
        $this->assertJsonResponse($response, 200);
        $content = $response->getContent();

        $this->assertContains('Neo4j', $content);

        $decoded = json_decode($content, true);
        $this->assertTrue(isset($decoded['volume_id']));
    }

    /**
     * Ability to brown book comments/reviews
     */
    public function testGetBrowseBookReviews()
    {
        $client = static::createClient();

        // at least one review for this book
        $crawler = $client->request('GET', '/api/browse/book/reviews', array('book_id' => '921'), array(), array('HTTP_ACCEPT' => 'application/json'));

        $response = $client->getResponse();
        $this->assertJsonResponse($response, 200);
        $content = $response->getContent();
        $decoded = json_decode($content, true);
        $this->assertTrue(sizeof($decoded) > 0);

        // No reviews for this book
        $crawler = $client->request('GET', '/api/browse/book/reviews', array('book_id' => '1263'), array(), array('HTTP_ACCEPT' => 'application/json'));

        $response = $client->getResponse();
        $this->assertJsonResponse($response, 200);
        $content = $response->getContent();
        $decoded = json_decode($content, true);
        $this->assertTrue(sizeof($decoded) == 0);
    }

    /**
     * Ability to add a comment to a book
     */
    public function testPostBookReview()
    {
        $this->getUserWithDID();

        $crawler = $this->client->request('POST', '/api/book/review?book_id=921&review=my+review', array('book_id' => '921', 'review' => 'My test review'), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));
        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 403);

        $content = $response->getContent();
        $this->assertContains('already reviewed this book', $content);

    }

    /**
     * Test book comment can only be edited by owner
     */
    public function testPostBookReviewNotOwnerEdit()
    {
        $this->getUserWithDID();

        $crawler = $this->client->request('POST', '/api/book/review/edit?review_id=930&review=my+review', array(), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));
        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 403);

        $content = $response->getContent();
        $this->assertContains('not the user that wrote this Book Review', $content);
    }

    public function testPostBookReviewEdit()
    {
        $this->getUserWithDID();

        $crawler = $this->client->request('POST', '/api/book/review/edit?review_id=929&review=my+edit+review', array(), array(), array('HTTP_ACCEPT' => 'application/json', 'Authorization' => 'Bearer: ' . $this->jwt));
        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, 200);

    }
}
