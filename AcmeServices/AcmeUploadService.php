<?php

namespace Acme\ApiBundle\Services;

use Aws\S3\S3Client;
use Acme\ApiBundle\Entity\Pin;

class AcmeUploadService
{

    protected $aws_region;
    protected $aws_key;
    protected $aws_secret;
    protected $aws_bucket;
    protected $aws_root;
    protected $aws_directory;
    protected $s3;

    /**
     * AcmeUploadService constructor.
     * @param $aws_region
     * @param $aws_key
     * @param $aws_secret
     * @param $aws_root
     * @param $aws_bucket
     * @param $aws_directory
     */
    public function __construct($aws_region, $aws_key, $aws_secret, $aws_root, $aws_bucket, $aws_directory)
    {
        $this->aws_region = $aws_region;
        $this->aws_key = $aws_key;
        $this->aws_secret = $aws_secret;
        $this->aws_root = $aws_root;
        $this->aws_bucket = $aws_bucket;
        $this->aws_directory = $aws_directory;

        $this->s3 = new S3Client([
            'version' => 'latest',
            'region' => $this->aws_region,
            'credentials' => array(
                'key' => $this->aws_key,
                'secret' => $this->aws_secret
            )
        ]);
    }

    /**
     * @param $localImagePath
     * @param $fileKey
     * @return string
     */
    protected function uploadLocal($localImagePath, $fileKey)
    {
        $this->s3->putObject([
            'Bucket' => $this->aws_bucket,
            'Key'    => $this->aws_directory . '/' . $fileKey,
            'Body'   => fopen($localImagePath, 'r'),
            'ACL'    => 'public-read',
            'ContentType' => mime_content_type($localImagePath)
        ]);

        return $this->aws_root . $this->aws_bucket . '/'. $this->aws_directory . '/' . $fileKey;
    }

    /**
     * @param $imageBase64
     * @param $fileKey
     * @return string
     */
    protected function uploadOriginalOnly($imageBase64, $fileKey)
    {
        $fileUploadPath = __DIR__.'/../../../../web/uploads/';

        $decoded = base64_decode($imageBase64);

        // get mime-type
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($fileInfo, $decoded, FILEINFO_MIME_TYPE);
        finfo_close($fileInfo);

        $extension = $this->mimeTypeToExt($mimeType);

        $fileKey .= $extension;

        file_put_contents($fileUploadPath . $fileKey, $decoded);

        $filePath = $fileUploadPath . $fileKey;

        // upload original image
        $this->s3->putObject([
            'Bucket' => $this->aws_bucket,
            'Key'    => $this->aws_directory . '/' . $fileKey,
            'Body'   => fopen($filePath, 'r'),
            'ACL'    => 'public-read',
            'ContentType' => $mimeType
        ]);

        return $this->aws_root . $this->aws_bucket . '/'. $this->aws_directory . '/' . $fileKey;
    }

    /**
     * @param $imageBase64
     * @param $fileKey
     * @param $thumbnailWidth
     * @param $thumbnailHeight
     * @param bool $keepOriginal
     * @return string
     */
    protected function upload($imageBase64, $fileKey, $thumbnailWidth, $thumbnailHeight, $keepOriginal = true)
    {
        $fileUploadPath = __DIR__.'/../../../../web/uploads/';

        $decoded = base64_decode($imageBase64);

        // get mime-type
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($fileInfo, $decoded, FILEINFO_MIME_TYPE);
        finfo_close($fileInfo);

        $extension = $this->mimeTypeToExt($mimeType);

        $thumbKey = $fileKey . '-' . $thumbnailWidth . 'x' . $thumbnailHeight;

        $fileKey .= $extension;
        $thumbKey .= $extension;

        file_put_contents($fileUploadPath . $fileKey, $decoded);

        $filePath = $fileUploadPath . $fileKey;
        $thumbnailPath = $fileUploadPath . $thumbKey;

        // Upload original image
        if ($keepOriginal)
        {
            $this->s3->putObject([
                'Bucket' => $this->aws_bucket,
                'Key'    => $this->aws_directory . '/' . $fileKey,
                'Body'   => fopen($filePath, 'r'),
                'ACL'    => 'public-read',
                'ContentType' => $mimeType
            ]);
        }

        // Make thumbnail
        $quality = array();
        if (stristr($extension, 'jpg'))
        {
            $quality = array('jpeg_quality' => 100);
        }
        if (stristr($extension, 'png'))
        {
            $quality = array('png_compression_level' => 9);
        }

        $imagine = new \Imagine\Gd\Imagine();
        $rotate = new \Imagine\Filter\Basic\Autorotate();
        $image = $imagine->open($filePath);
        $image = $rotate->apply($image);
        $thumbnail = $image->thumbnail(new \Imagine\Image\Box($thumbnailWidth, $thumbnailHeight));
        $thumbnail->save($thumbnailPath, $quality);

        $this->s3->putObject([
            'Bucket' => $this->aws_bucket,
            'Key'    => $this->aws_directory . '/' . $thumbKey,
            'Body'   => fopen($thumbnailPath, 'r'),
            'ACL'    => 'public-read',
            'ContentType' => $mimeType
        ]);

        return $this->aws_root . $this->aws_bucket . '/'. $this->aws_directory . '/' . $fileKey;

    }

    /**
     * @param $mimeType
     * @return mixed|string
     */
    private function mimeTypeToExt($mimeType)
    {
        $map = array(
            'image/gif'         => '.gif',
            'image/jpeg'        => '.jpg',
            'image/pjpeg'       => '.jpg',
            'image/bmp'         =>  '.bmp',
            'image/png'         => '.png',
        );

        if (isset($map[$mimeType]))
        {
            return $map[$mimeType];
        }
        else {
            return '.jpg';
        }
    }


}