<?php

namespace Acme\ApiBundle\Services;

use Acme\ApiBundle\Entity\Book;
use Google_Client;
use Google_Service_Books;
use Google_Service_Books_Volumes;
use Google_Service_Books_Volumes_Resource;

class GoogleApiBooksService {

    const PER_PAGE = 5;

    var $service;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
        $client = new Google_Client();
        $client->setApplicationName("Application Name");
        $client->setDeveloperKey($this->apiKey);

        $this->service = new Google_Service_Books($client);
    }

    /**
     * Get Google By by Volume Id
     * @param $volumeId
     * @return Google_Service_Books_Volumes
     */
    public function getById($volumeId)
    {
        /** @var Google_Service_Books_Volumes $results */
        $results = $this->service->volumes->get($volumeId);

        return $results;
    }

    /**
     * Excludes the search type so will full text search
     * @param $terms
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function searchGeneral($terms, $page = 0, $perPage = 0)
    {
        $results = $this->search($terms, '', $page, $perPage);

        return $this->formatSearchResults($results);
    }

    /**
     * Search Google Books by terms
     * @param $terms
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function searchBooks($terms, $page = 0, $perPage = 0)
    {
        $results = $this->search($terms, AcmeApiService::SEARCH_TYPE_BOOKS, $page, $perPage);

        return $this->formatSearchResults($results);
    }

    /**
     * Search Google Books by Author
     * @param $terms
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function searchAuthor($terms, $page = 0, $perPage = 0)
    {
        $results = $this->search($terms, AcmeApiService::SEARCH_TYPE_AUTHORS, $page, $perPage);

        return $this->formatSearchResults($results);
    }

    /**
     * Search Google Books by Genre
     * @param $terms
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function searchGenre($terms, $page = 0, $perPage = 0)
    {
        $results = $this->search($terms, AcmeApiService::SEARCH_TYPE_GENRES, $page, $perPage);

        return $this->formatSearchResults($results);

    }

    /**
     * Search Google Books by terms using pagination
     * @param $terms
     * @param $searchType
     * @param int $page
     * @param int $perPage
     * @return Google_Service_Books_Volumes
     */
    public function search($terms, $searchType, $page = 0, $perPage = 0) {

        $optParams = array(
            'printType' => 'books',
            'projection' => 'lite',
            'langRestrict' => 'en',
            'orderBy' => 'newest',
            'startIndex' => $page * $perPage,
            'maxResults' => $perPage

        );

        $terms = $this->addGoogleKeywords($terms, $searchType);

        /** @var Google_Service_Books_Volumes $results */
        $results = $this->service->volumes->listVolumes($terms, $optParams);


        return $results;
    }

    public function searchPartial($terms) {

        $optParams = array(
            'fields' => 'kind,items(id,volumeInfo/title,volumeInfo/authors)',
            'maxResults' => self::PER_PAGE
        );

        return $this->service->volumes->listVolumes($terms, $optParams);
    }

    public function getTotalItems($terms, $searchType)
    {
        $optParams = array(
            'fields' => 'kind,totalItems',
            'printType' => 'books',
            'projection' => 'lite',
            'langRestrict' => 'en',
        );
        $terms = $this->addGoogleKeywords($terms, $searchType);

        return $this->service->volumes->listVolumes($terms, $optParams);
    }

    private function formatSearchResults($results)
    {
        $books = array();
        foreach ($results as $item)
        {
            $books[] = array(
                'book' => self::formatBookResult($item)
            );
        }

        return $books;
    }

    static public function formatBookResult($item)
    {
        return  array(
            'source' => 'google',
            'id' => $item['id'],
            'volume_id' => $item['id'],
            'title' => $item['volumeInfo']['title'],
            'subtitle' => $item['volumeInfo']['subtitle'],
            'authors' => $item['volumeInfo']['authors'],
            'pageCount' => $item['volumeInfo']['pageCount'],
            'description' => $item['volumeInfo']['description'],
            'publishedDate' => $item['volumeInfo']['publishedDate'],
            'textSnippet' => $item['searchInfo']['textSnippet'],
            'thumbnail' => $item['volumeInfo']['imageLinks']['thumbnail']
        );
    }

    /**
     * There are special keywords you can specify in the search terms to search in particular fields
     * https://developers.google.com/books/docs/v1/using
     *
     * @param $terms
     * @param $searchType
     * @return string
     */
    private function addGoogleKeywords($terms, $searchType)
    {
        if ($searchType == AcmeApiService::SEARCH_TYPE_BOOKS)
        {
            $terms = urlencode('intitle:"'.$terms.'"');
        }
        if ($searchType == AcmeApiService::SEARCH_TYPE_AUTHORS)
        {
            $terms = urlencode('inauthor:"'.$terms.'"');
        }
        if ($searchType == AcmeApiService::SEARCH_TYPE_GENRES)
        {
            $terms = urlencode('subject:"'.$terms.'"');
        }

        return $terms;
    }

}