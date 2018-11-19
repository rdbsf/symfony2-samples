<?php

// Import script using formatted csv file to create product entities

namespace Acme\ApiBundle\Command;

use GraphAware\Neo4j\OGM\Common\Collection;
use Acme\ApiBundle\Entity\Product;
use Acme\ApiBundle\Entity\ProductCategory;
use Acme\ApiBundle\Entity\Room;
use Acme\ApiBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Finder\Finder;


use FOS\UserBundle\Command\CreateUserCommand as BaseCreateUserCommand;

class ProductsImportCommand extends ContainerAwareCommand
{
    protected $em;

    protected function configure()
    {
        $this
            ->setName('Acme:products:import')
            ->setDescription('Import set of products via csv file.');
    }

    private $cvsParsingOptions = array(
        'finder_in' => __DIR__ . '/import/',
        'finder_name' => 'products.csv',
        'ignoreFirstLine' => true
    );

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->em = $this->getContainer()->get('neo4j.entity_manager.default');
        $productUploadImageService = $this->getContainer()->get('Acme_api.Acme_product_upload_service');

        $csv = $this->parseCSV();

        foreach($csv as $line){

            $product = new Product();
            $product->setTitle($line[0]);
            $product->setSku($line[2]);
            // ...

            $this->setRooms($product, $line[11]);
            $this->setProductCategories($product, $line[10]);

            $this->em->persist($product);
            $this->em->flush();

            // Image upload
            $imagePath = $this->cvsParsingOptions['finder_in'] . $line[1];
            $uploadImagePath = $productUploadImageService->saveLocalProductImage($imagePath, $product->getId());

            // set product slug
            $slug = $this->getContainer()->get('Acme_api.Acme_slugify_service')->getProductSlug($product);
            $product->setSlug($slug);

            $product->setImg($uploadImagePath);
            $this->em->persist($product);
            $this->em->flush();
        }

        $output->writeln(sprintf('Done'));

    }


    /**
     * Assign rooms to product
     * @param $product
     * @param $rooms | ':' separated room names
     */
    private function setRooms(&$product, $rooms)
    {
        $roomCollection = new Collection();
        $rooms = explode(':', $rooms);
        foreach($rooms as $room)
        {
            $roomObj = $this->em->getRepository(Room::class)->findOneBy(['name' => $room]);
            if ($roomObj instanceof Room)
            {
                $roomCollection->add($roomObj);
            }
        }
        $product->setRooms($roomCollection);
    }

    /**
     * Assign rooms to product categories
     * @param $product
     * @param $rooms | ':' separated category names
     */
    private function setProductCategories(&$product, $categories)
    {
        $productCatCollection = new Collection();
        $productCats = explode(':', $categories);
        foreach($productCats as $productCat)
        {
            $productCatObj = $this->em->getRepository(ProductCategory::class)->findOneBy(['title' => $productCat]);
            if ($productCatObj instanceof ProductCategory)
            {
                $productCatCollection->add($productCatObj);
            }
        }
        $product->setProductCategories($productCatCollection);
    }

    /**
     * Parse csv file into an array
     * @return array
     * @throws \Exception
     */
    private function parseCSV()
    {
        $ignoreFirstLine = $this->cvsParsingOptions['ignoreFirstLine'];
        $finder = new Finder();
        $finder->files()
            ->in($this->cvsParsingOptions['finder_in'])
            ->name($this->cvsParsingOptions['finder_name'])
            ->files();
        foreach ($finder as $file) {
            $csv = $file;
        }
        if(empty($csv)){
            throw new \Exception("NO CSV FILE");
        }
        $rows = array();
        if (($handle = fopen($csv->getRealPath(), "r")) !== FALSE) {
            $i = 0;
            while (($data = fgetcsv($handle, null, ",")) !== FALSE) {
                $i++;
                if ($ignoreFirstLine && $i == 1) {
                    continue;
                }
                $rows[] = $data;
            }
            fclose($handle);
        }
        return $rows;
    }


}
