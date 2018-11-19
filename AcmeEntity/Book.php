<?php

namespace Acme\ApiBundle\Entity;

use HireVoice\Neo4j\Annotation as OGM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Book
 *
 * @OGM\Entity(labels="Book", repositoryClass="Acme\ApiBundle\Repositories\BookRepository")
 */
class Book
{

    const AMAZON_PRODUCT_NOT_FOUND = 'not_found';

    const PER_PAGE_MY_BOOKS = 10;

    const BOOK_SORT_DATE_DESC  = 'date_desc';
    const BOOK_SORT_DATE_ASC  = 'date_asc';
    //..

    static $bookSorting = array(
        self::BOOK_SORT_DATE_DESC,
        self::BOOK_SORT_DATE_ASC,
        //..
    );

    const USER_BOOK_STATUS_CURRENTLY_READING  = 'currently_reading';
    const USER_BOOK_STATUS_WANT_TO_READ  = 'want_to_read';
    const USER_BOOK_STATUS_HAS_READ  = 'has_read';

    /**
     * @var integer
     *
     * @OGM\Auto
     */
    private $id;

    /**
     * @var string
     *
     * @OGM\Property
     * @OGM\Index
     */
    private $title;

    /**
     * @var string
     *
     * @OGM\Property
     * @OGM\Index
     */
    private $volumeId;

    /**
     * @var array
     *
     * @OGM\Property(format="json")
     */
    private $authors;

//...

    /**
     * @var array
     *
     * @OGM\Property(format="json")
     */
    private $isbn;

    /**
     * @var array
     *
     * @OGM\Property(format="json")
     */
    private $categories;

    /**
     * Legacy, was converted to Want To Read
     * @OGM\ManyToMany(readOnly="true", relation="bookmarkedBook")
     */
    protected $bookmarkedBy;

    /**
     *
     * @OGM\ManyToMany(readOnly="true", relation="wantToRead")
     */
    protected $wantToReadBy;

    //..


    function __construct()
    {
        $this->currentlyReading = new ArrayCollection;
        $this->creationDate = new \DateTime();
        $this->altCoversByList = array();
        $this->amazonProductUrl = '';
        //..
    }

    public function asArray(){
        return array(
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'categories' => $this->getCategories(),
            //..
        );
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    public function getVolumeId()
    {
        return $this->volumeId;
    }

    public function setVolumeId($volumeId)
    {
        $this->volumeId = $volumeId;

        return $this;
    }

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    //...

    /**
     * @return string
     */
    public function getAmazonProductUrl()
    {
        return $this->amazonProductUrl;
    }

    /**
     * @param string $amazonProductUrl
     */
    public function setAmazonProductUrl($amazonProductUrl)
    {
        if (trim($amazonProductUrl) === '')
        {
            $this->setAmazonProductUrlNotFound();
        }
        else {
            $this->amazonProductUrl = $amazonProductUrl;
        }
    }

    public static function getBookSort($sort)
    {
        return in_array($sort, self::$bookSorting) ? $sort : self::BOOK_SORT_DATE_DESC;
    }

    public function validAmazonProductUrl()
    {
        return $this->amazonProductUrl != '' && !stristr($this->amazonProductUrl, 'xxxxxxxxx-xx') && $this->amazonProductUrl != self::AMAZON_PRODUCT_NOT_FOUND;
    }


    public function setAmazonProductUrlNotFound()
    {
        $this->setAmazonProductUrl(self::AMAZON_PRODUCT_NOT_FOUND);
    }

    public function isAmazonProductUrlNotFound()
    {
        return $this->amazonProductUrl == self::AMAZON_PRODUCT_NOT_FOUND;
    }


}

